<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\ActivityLog;
use App\Models\Room;
use App\Models\Review;
use App\Mail\BookingUpdate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
            'bookings' => $recentBookings,
            'chartLabels' => collect(range(0, 6))->map(fn ($day) => now()->subDays(6 - $day)->format('D')),
            'chartValues' => collect(range(0, 6))->map(fn ($day) => $bookingsByDay->get(now()->subDays(6 - $day)->toDateString(), collect())->count()),
        ]);
    }

    public function updateBooking(Request $request, Booking $booking)
    {
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
            $booking->room->update(['operational_status' => 'cleaning']);
            $this->log($booking, $request->user()->id, 'checked_out', 'Guest checked out; room moved to cleaning.');
            $this->email($booking->fresh('room'), 'Thank you for staying with Carolina', 'You have been checked out. Thank you for choosing Carolina.');
            return back()->with('success', 'Guest checked out. The room is now on the cleaning board.');
        }

        $status = $request->validate(['status' => 'required|in:pending,confirmed,cancelled'])['status'];
        $booking->update(['status' => $status, 'hold_expires_at' => $status === 'pending' ? now()->addMinutes(15) : null]);
        $this->log($booking, $request->user()->id, 'booking_' . $status, 'Booking status changed to ' . $status . '.');
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
            ->where('operational_status', 'available')
            ->orderBy('name')
            ->get();

        return view('admin.walk_in', compact('rooms'));
    }

    public function storeWalkIn(Request $request)
    {
        $this->refreshInventory();
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'required|string|max:30',
            'guests' => 'required|integer|min:1|max:20',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'payment_method' => 'required|in:gcash,cash',
        ]);

        $room = Room::findOrFail($data['room_id']);
        abort_if($room->operational_status !== 'available', 422, 'This room is not available for walk-ins.');

        $conflict = Booking::where('room_id', $room->id)
            ->blocking()
            ->where('check_in', '<', $data['check_out'])
            ->where('check_out', '>', $data['check_in'])
            ->exists();
        if ($conflict) {
            return back()->withInput()->withErrors(['room_id' => 'That room is already reserved for the selected dates.']);
        }

        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        Booking::create([
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
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'guests' => $data['guests'],
            'nights' => $nights,
            'total_amount' => $room->price_per_night * $nights,
            'status' => 'confirmed',
            'payment_method' => $data['payment_method'],
            'special_request' => 'Walk-in booking created by admin.',
        ]);

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

        return view('admin.bookings', [
            'period' => $period,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'occupiedRooms' => $rooms->where('display_status', 'occupied')->count(),
            'reservedRooms' => $rooms->where('display_status', 'reserved')->count(),
            'availableRooms' => $rooms->where('display_status', 'available')->count(),
            'maintenanceRooms' => $rooms->where('display_status', 'maintenance')->count(),
            'newCustomers' => Booking::with(['user', 'room'])->latest()->take(5)->get(),
            'individualBookings' => Booking::with(['user', 'room'])->latest()->paginate(15, ['*'], 'booking_page'),
            'arrivalsToday' => Booking::with('room')->where('status', 'confirmed')->whereDate('check_in', today())->whereNull('checked_in_at')->where('check_in_at', '<=', now())->get(),
            'departuresToday' => Booking::with('room')->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('check_out', '<=', today())->get(),
            'recentActivity' => ActivityLog::with(['booking.room', 'user'])->latest()->take(8)->get(),
            'pendingReviews' => Review::with(['booking.room', 'user'])->where('status', 'pending')->latest()->take(10)->get(),
            'recentReviews' => Review::with(['booking.room'])->whereIn('status', ['approved', 'hidden'])->latest()->take(8)->get(),
        ]);
    }

    public function updateReview(Request $request, Review $review)
    {
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

    public function updateRoomStatus(Request $request, Room $room)
    {
        $data = $request->validate([
            'operational_status' => 'required|in:available,cleaning,maintenance',
            'operational_until' => 'nullable|date|after:now',
        ]);
        if ($data['operational_status'] !== 'available' && empty($data['operational_until'])) {
            return back()->withErrors(['operational_until' => 'Set when this cleaning or maintenance block ends.']);
        }
        $room->update([
            'operational_status' => $data['operational_status'],
            'operational_until' => $data['operational_status'] === 'available' ? null : $data['operational_until'],
        ]);
        return back()->with('success', $data['operational_status'] === 'available' ? 'Room is available now.' : 'Room block saved and will end automatically at the selected time.');
    }

    private function roomsWithDisplayStatus()
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        return Room::where('is_active', true)->with(['bookings' => fn ($query) => $query
            ->with('user')
            ->blocking()
            ->where('check_out', '>', $today)
            ->orderBy('check_in')])
            ->orderBy('name')
            ->get()
            ->map(function (Room $room) use ($now) {
                $startsAt = fn (Booking $booking) => $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();
                $endsAt = fn (Booking $booking) => $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();
                $currentBooking = $room->bookings->first(fn (Booking $booking) => $startsAt($booking)->lte($now) && $endsAt($booking)->gt($now));
                $upcomingBooking = $room->bookings->first(fn (Booking $booking) => $startsAt($booking)->gt($now));
                $room->display_booking = $currentBooking ?? $upcomingBooking;
                $room->display_status = in_array($room->operational_status, ['cleaning', 'maintenance'], true)
                    ? $room->operational_status
                    : ($currentBooking ? 'occupied' : ($upcomingBooking ? 'reserved' : 'available'));

                return $room;
            });
    }

    private function refreshInventory(): void
    {
        Booking::releaseExpiredHolds();
        Room::releaseExpiredOperationalBlocks();
    }

    private function log(Booking $booking, ?int $userId, string $event, string $description): void
    {
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => $userId, 'event' => $event, 'description' => $description]);
    }

    private function email(Booking $booking, string $subject, string $message): void
    {
        $email = $booking->guest_email ?? $booking->user?->email;
        if ($email) Mail::to($email)->send(new BookingUpdate($booking, $subject, $message));
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
