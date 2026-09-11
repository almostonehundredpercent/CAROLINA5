<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\Review;
use App\Mail\BookingUpdate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index()
    {
        $this->refreshInventory();
        $recentBookings = Booking::with(['user', 'room'])->latest()->take(8)->get();
        $chartStart = now()->subDays(6)->startOfDay();
        $bookingsByDay = Booking::where('created_at', '>=', $chartStart)
            ->get()
            ->groupBy(fn (Booking $booking) => $booking->created_at->toDateString());

        return view('admin.admin_dashboard', [
            'bookingCount' => Booking::count(),
            'pendingCount' => Booking::where('status', 'pending')->count(),
            'confirmedCount' => Booking::where('status', 'confirmed')->count(),
            'roomCount' => Room::where('is_active', true)->count(),
            'revenue' => Booking::where('status', 'confirmed')->sum('total_amount'),
            'alerts' => [
                'arrivals' => Booking::where('status', 'confirmed')->whereNull('checked_in_at')->where('check_in_at', '<=', now())->count(),
                'departures' => Booking::whereNotNull('checked_in_at')->whereNull('checked_out_at')->where('check_out_at', '<=', now())->count(),
                'pending' => Booking::where('status', 'pending')->count(),
                'roomBlocks' => RoomBlock::where('starts_at', '<=', now()->addDay())->where('ends_at', '>', now())->count(),
            ],
            'bookings' => $recentBookings,
            'chartLabels' => collect(range(0, 6))->map(fn ($day) => now()->subDays(6 - $day)->format('D')),
            'chartValues' => collect(range(0, 6))->map(fn ($day) => $bookingsByDay->get(now()->subDays(6 - $day)->toDateString(), collect())->count()),
        ]);
    }

    public function updateBooking(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot manage bookings.');
        $action = $request->input('action');

        if ($action === 'check_in') {
            abort_if($booking->status !== 'confirmed' || $booking->checked_out_at, 422, 'Only confirmed bookings can be checked in.');
            abort_if($booking->check_in_at && $booking->check_in_at->isFuture(), 422, 'This guest cannot be checked in before the scheduled arrival time.');
            $booking->update(['checked_in_at' => now(), 'status' => 'confirmed']);
            $this->log($booking, $request->user()->id, 'checked_in', 'Guest checked in by staff.');
            $this->email($booking->fresh('room'), 'Welcome to Carolina', 'You have been checked in. We hope you enjoy your stay.');
            return back()->with('success', 'Guest checked in and notified.');
        }
        if ($action === 'check_out') {
            abort_if(! $booking->checked_in_at || $booking->checked_out_at, 422, 'This guest is not currently checked in.');
            $booking->update(['checked_out_at' => now()]);
            RoomBlock::create(['room_id' => $booking->room_id, 'status' => 'cleaning', 'starts_at' => now(), 'ends_at' => now()->addHour(), 'notes' => 'Automatic cleaning period after check-out.', 'created_by' => $request->user()->id]);
            $this->log($booking, $request->user()->id, 'checked_out', 'Guest checked out; room moved to cleaning.');
            $this->email($booking->fresh('room'), 'Thank you for staying with Carolina', 'You have been checked out. Thank you for choosing Carolina.');
            return back()->with('success', 'Guest checked out. A one-hour cleaning block is now active.');
        }

        $status = $request->validate(['status' => 'required|in:pending,confirmed,cancelled', 'staff_note' => 'nullable|string|max:500'])['status'];
        abort_if($status === 'cancelled' && $booking->checked_in_at && ! $booking->checked_out_at, 422, 'Check out the guest before cancelling their booking.');
        if ($status === 'confirmed') {
            DB::transaction(function () use ($booking) {
                $lockedRoom = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
                $startsAt = $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();
                $endsAt = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();
                $conflict = $lockedRoom->bookings()->whereKeyNot($booking->id)->blocking()->overlapping($startsAt, $endsAt)->exists()
                    || $lockedRoom->blocks()->overlapping($startsAt, $endsAt)->exists();
                if ($conflict) throw ValidationException::withMessages(['status' => 'This room is no longer available for the requested stay.']);
                $booking->update(['status' => 'confirmed', 'hold_expires_at' => null]);
            });
        } else {
            $booking->update(['status' => $status, 'hold_expires_at' => null]);
        }
        $note = trim((string) $request->input('staff_note'));
        if ($note !== '') $booking->update(['staff_notes' => trim(($booking->staff_notes ? $booking->staff_notes . "\n" : '') . now()->format('Y-m-d H:i') . ' · ' . $request->user()->name . ': ' . $note)]);
        $this->log($booking, $request->user()->id, 'booking_' . $status, 'Booking status changed to ' . $status . ($note ? '. Note: ' . $note : '.'));
        $this->email($booking->fresh('room'), 'Your Carolina booking was updated', 'Your booking status is now ' . $status . '.');
        return back()->with('success', 'Booking status updated.');
    }

    public function walkInForm()
    {
        $this->refreshInventory();
        // Future reservations are handled by the date calendar. Only rooms that
        // are currently unavailable for operational reasons are excluded here.
        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.walk_in', compact('rooms'));
    }

    public function storeWalkIn(Request $request)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot create walk-ins.');
        $this->refreshInventory();
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'required|string|max:30',
            'guests' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
        ]);

        $checkInAt = Carbon::parse($data['check_in'] . ' ' . ($data['check_in_time'] ?? '12:00'));
        $checkOutAt = Carbon::parse($data['check_out'] . ' ' . ($data['check_out_time'] ?? '12:00'));
        if ($checkOutAt->lte($checkInAt)) return back()->withInput()->withErrors(['check_out_time' => 'Check-out must be after check-in.']);
        $nights = max(1, (int) ceil($checkInAt->diffInHours($checkOutAt) / 24));
        $booking = DB::transaction(function () use ($data, $checkInAt, $checkOutAt, $nights, $request) {
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            if ($data['guests'] > $room->guests) throw ValidationException::withMessages(['guests' => "{$room->name} accommodates up to {$room->guests} guests."]);
            $conflict = $room->bookings()->blocking()->overlapping($checkInAt, $checkOutAt)->exists()
                || $room->blocks()->overlapping($checkInAt, $checkOutAt)->exists();
            if ($conflict) throw ValidationException::withMessages(['room_id' => 'That room is unavailable for the selected stay.']);
            return Booking::create([
            'user_id' => null,
            'room_id' => $room->id,
            'guest_name' => $data['guest_name'],
            'guest_email' => $data['guest_email'],
            'guest_phone' => $data['guest_phone'],
            'billing_street' => 'Walk-in guest',
            'billing_city' => 'Tabaco City',
            'billing_province' => 'Albay',
            'billing_postal_code' => '4511',
            'billing_verified_at' => now(),
            'check_in' => $checkInAt->toDateString(),
            'check_out' => $checkOutAt->toDateString(),
            'check_in_at' => $checkInAt,
            'check_out_at' => $checkOutAt,
            'guests' => $data['guests'],
            'nights' => $nights,
            'total_amount' => $room->price_per_night * $nights,
            'status' => 'confirmed',
            'payment_method' => 'cash',
            'special_request' => 'Walk-in booking created by admin.',
            ]);
        });

        return redirect()->route('admin.bookings')->with('success', 'Walk-in booking confirmed.');
    }

    public function rooms()
    {
        $this->refreshInventory();
        $rooms = $this->roomsWithDisplayStatus();

        return view('admin.rooms', compact('rooms'));
    }

    public function bookings(Request $request)
    {
        $this->refreshInventory();
        $period = $request->string('period', 'daily')->value();
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true), 404);

        [$chartLabels, $chartValues] = $this->bookingChartData($period);
        $rooms = $this->roomsWithDisplayStatus();

        $bookingQuery = Booking::with(['user', 'room'])->latest();
        if ($request->filled('status')) $bookingQuery->where('status', $request->string('status')->value());
        if ($request->filled('arrival')) {
            $arrival = $request->string('arrival')->value();
            $bookingQuery->where(function ($query) use ($arrival) {
                $query->whereDate('check_in', $arrival)->orWhereDate('check_out', $arrival);
            });
        }
        if ($request->filled('search')) {
            $search = '%' . $request->string('search')->value() . '%';
            $bookingQuery->where(fn ($query) => $query->where('reference', 'like', $search)->orWhere('guest_name', 'like', $search)->orWhere('guest_email', 'like', $search));
        }

        return view('admin.bookings', [
            'period' => $period,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'occupiedRooms' => $rooms->where('display_status', 'occupied')->count(),
            'reservedRooms' => $rooms->where('display_status', 'reserved')->count(),
            'availableRooms' => $rooms->where('display_status', 'available')->count(),
            'maintenanceRooms' => $rooms->where('display_status', 'maintenance')->count(),
            'newCustomers' => Booking::with(['user', 'room'])->latest()->take(5)->get(),
            'individualBookings' => $bookingQuery->paginate(15, ['*'], 'booking_page')->withQueryString(),
            'arrivalsToday' => Booking::with('room')->where('status', 'confirmed')->whereDate('check_in', today())->whereNull('checked_in_at')->where('check_in_at', '<=', now())->get(),
            'departuresToday' => Booking::with('room')->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('check_out', '<=', today())->get(),
            'recentActivity' => ActivityLog::with(['booking.room', 'user'])->latest()->take(8)->get(),
            'pendingReviews' => Review::with(['booking.room', 'user'])->where('status', 'pending')->latest()->take(10)->get(),
            'recentReviews' => Review::with(['booking.room'])->whereIn('status', ['approved', 'hidden'])->latest()->take(8)->get(),
        ]);
    }

    public function updateReview(Request $request, Review $review)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot moderate reviews.');
        $data = $request->validate(['status' => 'required|in:approved,hidden']);
        $review->update([
            'status' => $data['status'],
            'approved_at' => $data['status'] === 'approved' ? now() : null,
            'approved_by' => $data['status'] === 'approved' ? $request->user()->id : null,
        ]);
        $this->log($review->booking, $request->user()->id, 'review_' . $data['status'], 'Guest review ' . $data['status'] . ' by staff.');

        return back()->with('success', 'Review ' . $data['status'] . '.');
    }

    public function reports(Request $request)
    {
        $this->refreshInventory();
        $period = $request->string('period', 'daily')->value();
        $metric = $request->string('metric', 'earnings')->value();
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true), 404);
        abort_unless(in_array($metric, ['earnings', 'bookings', 'guests'], true), 404);

        [$dates, $keys, $labels] = $this->reportPeriods($period);
        $bookings = Booking::with(['user', 'room'])
            ->where('created_at', '>=', $dates->first())
            ->get();
        $grouped = $bookings->groupBy(fn (Booking $booking) => $this->reportKey($booking->created_at, $period));
        $values = $keys->map(function (string $key) use ($grouped, $metric) {
            $items = $grouped->get($key, collect());
            return match ($metric) {
                'earnings' => (float) $items->where('status', 'confirmed')->sum('total_amount'),
                default => $items->count(),
            };
        });
        $titles = ['earnings' => ['Total earnings', 'Revenue'], 'bookings' => ['Total bookings', 'Bookings'], 'guests' => ['Guest bookings', 'Bookings']];
        $rooms = $metric === 'bookings' ? $this->roomsWithDisplayStatus() : collect();
        $guestBookings = $metric === 'guests' ? Booking::with('user')->get() : collect();
        $guestGroups = $metric === 'guests' ? $guestBookings->groupBy(fn (Booking $booking) => $this->guestKey($booking)) : collect();

        return view('admin.reports', [
            'metric' => $metric,
            'period' => $period,
            'metricTitle' => $titles[$metric][0],
            'chartTitle' => $titles[$metric][1],
            'total' => $values->sum(),
            'chartLabels' => $labels,
            'chartValues' => $values,
            'latestBookings' => Booking::with(['user', 'room'])->latest()->take(5)->get(),
            'roomPerformance' => Booking::with('room')->where('status', 'confirmed')->get()->groupBy('room_id')->map(fn ($items) => ['room' => $items->first()->room?->name ?? 'Room removed', 'bookings' => $items->count(), 'revenue' => (float) $items->sum('total_amount')])->sortByDesc('revenue')->take(5),
            'bookingReportMetrics' => $metric === 'bookings' ? [
                'total' => Booking::count(),
                'cancelled' => Booking::where('status', 'cancelled')->count(),
                'occupancy' => $rooms->count() ? round($rooms->where('display_status', 'occupied')->count() / $rooms->count() * 100) : 0,
                'completed' => Booking::where('status', 'confirmed')->where('check_out', '<=', now()->toDateString())->count(),
            ] : null,
            'guestReportMetrics' => $metric === 'guests' ? [
                'total' => $guestGroups->count(),
                'new' => $guestGroups->filter(fn ($bookings) => $bookings->min('created_at')->gte($dates->first()))->count(),
                'returning' => $guestGroups->filter(fn ($bookings) => $bookings->count() > 1)->count(),
                'averageStay' => round((float) ($guestBookings->avg('nights') ?? 0), 1),
            ] : null,
        ]);
    }

    public function staff(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only administrators can manage staff roles.');

        return view('admin.staff', ['staff' => \App\Models\User::where('is_admin', true)->orWhereIn('staff_role', ['front_desk', 'housekeeping', 'viewer'])->orderBy('name')->get()]);
    }

    public function updateStaffRole(Request $request, \App\Models\User $user)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only administrators can manage staff roles.');
        abort_if($user->id === $request->user()->id && $request->input('staff_role') !== 'admin', 422, 'You cannot remove your own administrator access.');
        $role = $request->validate(['staff_role' => 'required|in:admin,front_desk,housekeeping,viewer,guest'])['staff_role'];
        $user->update(['staff_role' => $role, 'is_admin' => $role === 'admin']);

        return back()->with('success', $user->name . "'s staff role was updated.");
    }

    public function updateRoomStatus(Request $request, Room $room)
    {
        abort_unless($request->user()->canManageRooms(), 403, 'Your staff role cannot manage room operations.');
        $data = $request->validate([
            'operational_status' => 'required|in:available,cleaning,maintenance',
            'operational_starts_at' => 'nullable|date',
            'operational_until' => 'nullable|date|after:operational_starts_at',
            'notes' => 'nullable|string|max:255',
            'force_override' => 'nullable|boolean',
        ]);
        if ($data['operational_status'] === 'available') {
            $room->blocks()->where('ends_at', '>', now())->where('starts_at', '<=', now())->delete();
            return back()->with('success', 'Active room blocks cleared.');
        }
        if (empty($data['operational_starts_at']) || empty($data['operational_until'])) return back()->withErrors(['operational_until' => 'Set both a start and end time for this room block.']);
        $startsAt = Carbon::parse($data['operational_starts_at']);
        $endsAt = Carbon::parse($data['operational_until']);
        $overlap = $room->bookings()->blocking()->overlapping($startsAt, $endsAt)->exists();
        $override = (bool) ($data['force_override'] ?? false);
        if ($overlap && (! $override || ! $request->user()->isAdmin())) {
            return back()->withErrors(['operational_until' => 'This operation overlaps a confirmed stay. Choose another time, or have an administrator record an override.']);
        }
        $notes = $data['notes'] ?? null;
        if ($overlap && $override) $notes = trim(($notes ? $notes . ' · ' : '') . 'Manager override by ' . $request->user()->name);
        RoomBlock::create(['room_id' => $room->id, 'status' => $data['operational_status'], 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'notes' => $notes, 'created_by' => $request->user()->id]);
        return back()->with('success', $overlap ? 'Scheduled room block saved with manager override.' : 'Scheduled room block saved.');
    }

    private function roomsWithDisplayStatus()
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        return Room::where('is_active', true)->with(['bookings' => fn ($query) => $query
            ->with('user')
            ->blocking()
            ->where('check_out', '>', $today)
            ->orderBy('check_in'), 'blocks' => fn ($query) => $query->where('ends_at', '>', $now)->orderBy('starts_at')])
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($now) {
                $startsAt = fn (Booking $booking) => $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();
                $endsAt = fn (Booking $booking) => $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();
                $currentBooking = $room->bookings->first(fn (Booking $booking) => $startsAt($booking)->lte($now) && $endsAt($booking)->gt($now));
                $upcomingBooking = $room->bookings->first(fn (Booking $booking) => $startsAt($booking)->gt($now));
                $currentBlock = $room->blocks->first(fn (RoomBlock $block) => $block->starts_at->lte($now) && $block->ends_at->gt($now));
                $upcomingBlock = $room->blocks->first(fn (RoomBlock $block) => $block->starts_at->gt($now));
                $room->display_booking = $currentBooking ?? $upcomingBooking;
                $room->display_block = $currentBlock ?? $upcomingBlock;
                $room->display_status = $currentBlock?->status ?? ($currentBooking ? 'occupied' : ($upcomingBooking ? 'reserved' : 'available'));

                return $room;
            });
    }

    private function refreshInventory(): void
    {
        RoomBlock::where('ends_at', '<=', now())->delete();
    }

    private function log(Booking $booking, ?int $userId, string $event, string $description): void
    {
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => $userId, 'event' => $event, 'description' => $description]);
    }

    private function email(Booking $booking, string $subject, string $message): void
    {
        $email = $booking->guest_email ?? $booking->user?->email;
        if (! $email) return;
        try {
            Mail::to($email)->send(new BookingUpdate($booking, $subject, $message));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function bookingChartData(string $period): array
    {
        $now = now();
        $configuration = match ($period) {
            'weekly' => [4, fn (int $index) => $now->copy()->subWeeks(3 - $index)->startOfWeek(), 'W'],
            'monthly' => [12, fn (int $index) => $now->copy()->subMonths(11 - $index)->startOfMonth(), 'M'],
            'yearly' => [5, fn (int $index) => $now->copy()->subYears(4 - $index)->startOfYear(), 'Y'],
            default => [7, fn (int $index) => $now->copy()->subDays(6 - $index)->startOfDay(), 'D'],
        };

        [$count, $dateForIndex, $format] = $configuration;
        $dates = collect(range(0, $count - 1))->map($dateForIndex);
        $bookings = Booking::where('created_at', '>=', $dates->first())->get();
        $keys = $dates->map(fn (Carbon $date) => match ($period) {
            'weekly' => $date->format('o-W'),
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        });
        $grouped = $bookings->groupBy(fn (Booking $booking) => match ($period) {
            'weekly' => $booking->created_at->format('o-W'),
            'monthly' => $booking->created_at->format('Y-m'),
            'yearly' => $booking->created_at->format('Y'),
            default => $booking->created_at->format('Y-m-d'),
        });

        return [$dates->map(fn (Carbon $date) => $period === 'weekly' ? 'Week ' . $date->weekOfYear : $date->format($format)), $keys->map(fn (string $key) => $grouped->get($key, collect())->count())];
    }

    private function reportPeriods(string $period): array
    {
        $now = now();
        $configuration = match ($period) {
            'weekly' => [4, fn (int $index) => $now->copy()->subWeeks(3 - $index)->startOfWeek(), fn (Carbon $date) => 'Week ' . $date->weekOfYear],
            'monthly' => [12, fn (int $index) => $now->copy()->subMonths(11 - $index)->startOfMonth(), fn (Carbon $date) => $date->format('M')],
            'yearly' => [5, fn (int $index) => $now->copy()->subYears(4 - $index)->startOfYear(), fn (Carbon $date) => $date->format('Y')],
            default => [7, fn (int $index) => $now->copy()->subDays(6 - $index)->startOfDay(), fn (Carbon $date) => $date->format('D')],
        };
        [$count, $dateForIndex, $labelForDate] = $configuration;
        $dates = collect(range(0, $count - 1))->map($dateForIndex);
        return [$dates, $dates->map(fn (Carbon $date) => $this->reportKey($date, $period)), $dates->map($labelForDate)];
    }

    private function reportKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'weekly' => $date->format('o-W'),
            'monthly' => $date->format('Y-m'),
            'yearly' => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    private function guestKey(Booking $booking): string
    {
        return strtolower($booking->guest_email ?? $booking->user?->email ?? 'booking-' . $booking->id);
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
