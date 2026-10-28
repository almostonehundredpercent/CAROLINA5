<?php

namespace App\Http\Controllers;

use App\Mail\BookingUpdate;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use App\Models\GuestNote;
use App\Models\GuestRestriction;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\User;
use App\Services\OperationalDecisionSupport;
use App\Services\PayMongoCheckout;
use App\Services\PayMongoPaymentReconciler;
use App\Support\AdminPermissions;
use App\Support\FinancialSummary;
use App\Support\PropertyTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function index(OperationalDecisionSupport $decisionSupport)
    {
        if (AdminPermissions::role(request()->user()) === 'viewer') {
            $rooms = $this->roomsWithDisplayStatus();

            return view('admin.viewer-dashboard', compact('rooms'));
        }
        try {
            try {
                $this->refreshInventory();
            } catch (QueryException $exception) {
                // Existing inventory is still shown below; only the automatic
                // completion marker is deferred until the database is updated.
                Log::warning('Room-block completion refresh skipped.', ['exception' => $exception]);
            }
            [$revenue, $paymentPending, $paymentSourceLabel] = $this->paymentDashboardMetrics();
            $recentBookings = Booking::with(['user', 'room'])->latest()->take(8)->get();
            $rooms = $this->roomsWithDisplayStatus();
            $chartStart = now()->subDays(6)->startOfDay();
            $bookingsByDay = Booking::where('created_at', '>=', $chartStart)
                ->get()
                ->groupBy(fn (Booking $booking) => $booking->created_at->toDateString());

            return view('admin.admin_dashboard', [
                'viewer' => AdminPermissions::role(request()->user()) === 'viewer',
                'bookingCount' => Booking::count(),
                'pendingCount' => Booking::where('status', 'pending')->count(),
                'confirmedCount' => Booking::where('status', 'confirmed')->count(),
                'roomCount' => $rooms->count(),
                'revenue' => $revenue,
                'paymentPending' => $paymentPending,
                'paymentSourceLabel' => $paymentSourceLabel,
                'checkedInCount' => Booking::whereNotNull('checked_in_at')->whereNull('checked_out_at')->count(),
                'checkedOutCount' => Booking::whereNotNull('checked_out_at')->count(),
                'cancelledCount' => Booking::where('status', 'cancelled')->count(),
                'roomsByStatus' => [
                    'available' => $rooms->where('display_status', 'available')->count(),
                    'occupied' => $rooms->where('display_status', 'occupied')->count(),
                    'maintenance' => $rooms->whereIn('display_status', ['maintenance', 'cleaning'])->count(),
                ],
                'alerts' => [
                    'arrivals' => Booking::where('status', 'confirmed')->whereNull('checked_in_at')->where('check_in_at', '<=', now())->count(),
                    'departures' => Booking::whereNotNull('checked_in_at')->whereNull('checked_out_at')->where('check_out_at', '<=', now())->count(),
                    'pending' => Booking::where('status', 'pending')->count(),
                    'roomBlocks' => RoomBlock::where('starts_at', '<=', now()->addDay())->where('ends_at', '>', now())->count(),
                ],
                'bookings' => $recentBookings,
                'recentActivity' => ActivityLog::with(['booking.room', 'user'])->latest()->take(6)->get(),
                'topRooms' => Booking::query()->where('status', 'confirmed')->selectRaw('room_id, count(*) as bookings_count')->groupBy('room_id')->orderByDesc('bookings_count')->with('room')->take(3)->get(),
                'turnovers' => $decisionSupport->upcomingTurnovers(),
                'chartLabels' => collect(range(0, 6))->map(fn ($day) => now()->subDays(6 - $day)->format('D')),
                'chartValues' => collect(range(0, 6))->map(fn ($day) => $bookingsByDay->get(now()->subDays(6 - $day)->toDateString(), collect())->count()),
                'recommendations' => $decisionSupport->dashboardSignals($rooms),
            ]);
        } catch (\Throwable $exception) {
            // The dashboard is an operational overview. A non-essential
            // summary query must never lock staff out of the admin area.
            Log::error('Admin dashboard summary failed; rendering safe fallback.', [
                'exception' => $exception,
            ]);

            $bookingCount = Booking::count();
            $pendingCount = Booking::where('status', 'pending')->count();
            $confirmedCount = Booking::where('status', 'confirmed')->count();
            $cancelledCount = Booking::where('status', 'cancelled')->count();
            $roomCount = Room::where('is_active', true)->count();

            return view('admin.admin_dashboard', [
                'viewer' => AdminPermissions::role(request()->user()) === 'viewer',
                'bookingCount' => $bookingCount,
                'pendingCount' => $pendingCount,
                'confirmedCount' => $confirmedCount,
                'roomCount' => $roomCount,
                'revenue' => Booking::where('payment_status', 'paid')->sum('total_amount'),
                'paymentPending' => Booking::where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->count(),
                'paymentSourceLabel' => 'Legacy booking payment records (ledger unavailable)',
                'checkedInCount' => 0,
                'checkedOutCount' => 0,
                'cancelledCount' => $cancelledCount,
                'roomsByStatus' => ['available' => $roomCount, 'occupied' => 0, 'maintenance' => 0],
                'alerts' => ['arrivals' => 0, 'departures' => 0, 'pending' => $pendingCount, 'roomBlocks' => 0],
                'bookings' => Booking::with(['user', 'room'])->latest()->take(8)->get(),
                'recentActivity' => collect(),
                'topRooms' => collect(),
                'turnovers' => collect(),
                'chartLabels' => collect(range(0, 6))->map(fn ($day) => now()->subDays(6 - $day)->format('D')),
                'chartValues' => collect(array_fill(0, 7, 0)),
                'recommendations' => collect([[
                    'title' => 'Operational summary temporarily unavailable',
                    'evidence' => 'The dashboard is showing core booking data while a non-essential operational summary is restored.',
                    'rule' => 'This message appears when an operational summary query fails.',
                    'action_label' => 'Review bookings',
                    'action_url' => route('admin.bookings'),
                ]]),
            ]);
        }
    }

    public function updateBooking(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot manage bookings.');
        $action = $request->input('action');

        if ($action === 'check_in') {
            abort_if($booking->status !== 'confirmed' || $booking->checked_out_at, 422, 'Only confirmed bookings can be checked in.');
            abort_if($booking->check_in_at && $booking->check_in_at->isFuture(), 422, 'This guest cannot be checked in before the scheduled arrival time.');
            $booking->update(['checked_in_at' => PropertyTime::now(), 'status' => 'confirmed']);
            $this->log($booking, $request->user()->id, 'checked_in', 'Guest checked in by staff.');
            $this->email($booking->fresh('room'), 'Welcome to Carolina', 'You have been checked in. We hope you enjoy your stay.');

            return back()->with('success', 'Guest checked in and notified.');
        }
        if ($action === 'check_out') {
            abort_if(! $booking->checked_in_at || $booking->checked_out_at, 422, 'This guest is not currently checked in.');
            DB::transaction(function () use ($booking, $request) {
                $lockedRoom = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
                $checkedOutAt = PropertyTime::now();
                $booking->update(['checked_out_at' => $checkedOutAt]);
                RoomBlock::create([
                    'room_id' => $lockedRoom->id,
                    'status' => 'cleaning',
                    'starts_at' => $checkedOutAt,
                    'ends_at' => $checkedOutAt->copy()->addMinutes(20),
                    'notes' => 'Automatic 20-minute cleaning period after check-out.',
                    'created_by' => $request->user()->id,
                ]);
            });
            $this->log($booking, $request->user()->id, 'checked_out', 'Guest checked out; 20-minute room cleaning timer started.');
            $this->email($booking->fresh('room'), 'Thank you for staying with Carolina', 'You have been checked out. Thank you for choosing Carolina.');

            return back()->with('success', 'Guest checked out. A 20-minute cleaning timer is now active.');
        }

        $data = $request->validate(['status' => 'required|in:pending,confirmed,cancelled', 'staff_note' => 'nullable|string|max:500', 'cancellation_reason' => 'nullable|string|max:500']);
        $status = $data['status'];
        $before = DB::transaction(function () use ($booking, $status, $data, $request) {
            $lockedRoom = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $before = $lockedBooking->only(['status', 'staff_notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
            if ($status === 'confirmed') {
                if ($lockedBooking->status !== 'pending' && ! $lockedBooking->isExpiredHoldRecord()) {
                    throw ValidationException::withMessages(['status' => 'This booking is no longer pending. Refresh the bookings page before taking action.']);
                }
                if (! $lockedRoom->is_active || $lockedRoom->operational_status !== 'available') {
                    throw ValidationException::withMessages(['status' => 'This room is not ready to book. Restore it only after the room is active and available.']);
                }
                $startsAt = $lockedBooking->check_in_at ?? $lockedBooking->check_in->copy()->startOfDay();
                $endsAt = $lockedBooking->check_out_at ?? $lockedBooking->check_out->copy()->startOfDay();
                $conflict = $lockedRoom->bookings()->whereKeyNot($lockedBooking->id)->blocking()->overlapping($startsAt, $endsAt)->exists()
                    || $lockedRoom->blocks()->overlapping($startsAt, $endsAt)->exists();
                if ($conflict) {
                    throw ValidationException::withMessages(['status' => 'This room is no longer available for the requested stay.']);
                }
                $lockedBooking->update(['status' => 'confirmed', 'hold_expires_at' => null, 'cancelled_at' => null, 'cancelled_by' => null, 'cancellation_reason' => null]);
            } else {
                abort_if($status === 'cancelled' && $lockedBooking->checked_in_at && ! $lockedBooking->checked_out_at, 422, 'Check out the guest before cancelling their booking.');
                $lockedBooking->update(['status' => $status, 'hold_expires_at' => null] + ($status === 'cancelled' ? [
                    'cancelled_at' => now(),
                    'cancelled_by' => $request->user()->id,
                    'cancellation_reason' => trim((string) ($data['cancellation_reason'] ?? '')) ?: 'Cancelled by staff.',
                ] : ['cancelled_at' => null, 'cancelled_by' => null, 'cancellation_reason' => null]));
            }

            return $before;
        });
        $booking->refresh();
        $note = trim((string) $request->input('staff_note'));
        if ($note !== '') {
            $booking->update(['staff_notes' => trim(($booking->staff_notes ? $booking->staff_notes."\n" : '').now()->format('Y-m-d H:i').' · '.$request->user()->name.': '.$note)]);
        }
        $this->log($booking->fresh(), $request->user()->id, 'booking_'.$status, 'Booking status changed to '.$status.($note ? '. Note: '.$note : '.'), $before, $booking->fresh()->only(['status', 'staff_notes', 'cancelled_at', 'cancelled_by', 'cancellation_reason']));
        $this->email($booking->fresh('room'), 'Your Carolina booking was updated', 'Your booking status is now '.$status.'.');

        return back()->with('success', 'Booking status updated.');
    }

    public function updatePayment(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot manage payments.');
        $data = $request->validate([
            'payment_status' => 'required|in:pending,paid,failed',
            'payment_method' => 'required|in:cash,gcash',
        ]);
        abort_if($booking->status === 'cancelled' && $data['payment_status'] === 'paid', 422, 'A cancelled booking cannot be marked paid.');
        $before = $booking->only(['payment_status', 'payment_method', 'paid_at']);
        $paidAt = $data['payment_status'] === 'paid' ? now() : null;
        DB::transaction(function () use ($booking, $data, $paidAt, $request) {
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $hasPayMongoCheckout = Payment::where('booking_id', $lockedBooking->id)
                ->where('reference', 'like', 'paymongo:%')
                ->whereIn('status', ['pending', 'paid', 'refunded'])
                ->exists();
            if ($hasPayMongoCheckout) {
                throw ValidationException::withMessages(['payment_status' => 'PayMongo payments must be verified with PayMongo. Use the Verify with PayMongo action instead.']);
            }
            abort_if($data['payment_status'] === 'paid' && $lockedBooking->status === 'cancelled', 422, 'A cancelled booking cannot be marked paid.');
            if ($data['payment_status'] === 'paid' && $lockedBooking->hasExpiredHold()) {
                throw ValidationException::withMessages(['payment_status' => 'This booking hold expired. Check room availability and restore the booking before recording cash payment.']);
            }
            Payment::create(['booking_id' => $lockedBooking->id, 'amount' => $lockedBooking->total_amount, 'method' => $data['payment_method'], 'status' => $data['payment_status'], 'paid_at' => $paidAt, 'recorded_by' => $request->user()->id, 'notes' => 'Manual staff payment record.']);
            $lockedBooking->update($data + ['paid_at' => $paidAt]);
        });
        $booking->refresh();
        $this->log($booking->fresh(), $request->user()->id, 'payment_'.$data['payment_status'], 'Payment status recorded as '.$data['payment_status'].'.', $before, $booking->fresh()->only(['payment_status', 'payment_method', 'paid_at']));
        $this->email($booking->fresh('room'), 'Payment update for your Carolina booking', 'Your payment status is now '.$data['payment_status'].'.');

        return back()->with('success', 'Payment record updated.');
    }

    public function recheckPayMongoPayment(Request $request, Booking $booking, PayMongoCheckout $checkout, PayMongoPaymentReconciler $payments)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot manage payments.');

        $payment = $booking->payments()
            ->where('method', 'gcash')
            ->where('status', 'pending')
            ->where('reference', 'like', 'paymongo:%')
            ->latest('id')
            ->first();
        if (! $payment) {
            return back()->withErrors(['payment_status' => 'There is no pending PayMongo checkout to verify.']);
        }

        try {
            $session = $checkout->retrieve(substr($payment->reference, strlen('paymongo:')));
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['payment_status' => 'PayMongo could not be reached. The payment remains pending; try verification again shortly.']);
        }

        if (! $payments->isMatchingPaidCheckout($booking, $payment, $session)) {
            return back()->withErrors(['payment_status' => 'PayMongo has not confirmed this matching payment yet. No booking times were changed.']);
        }

        $payments->recordPaid($booking, $payment, $request->user()->id);
        $payment->refresh();
        if ($payment->status !== 'paid') {
            return back()->withErrors(['payment_status' => 'PayMongo verified this checkout, but the booking record could not be updated. Please try again or contact support.']);
        }
        $booking->refresh();
        $adjustment = BookingTimeAdjustment::where('payment_id', $payment->id)->first();
        if ($adjustment) {
            return back()->with('success', $adjustment->status === 'applied'
                ? 'PayMongo confirmed the extra-time payment. The new booking time is active.'
                : 'PayMongo received the extra-time payment, but the room time needs staff follow-up.');
        }

        return back()->with('success', $booking->status === 'cancelled'
            ? 'PayMongo confirmed payment, but the reservation is no longer active. Carolina staff must resolve the booking.'
            : ($booking->status === 'confirmed'
                ? 'PayMongo confirmed the full payment; the booking was confirmed automatically.'
                : 'PayMongo confirmed the full payment. Carolina staff must resolve the booking.'));
    }

    /**
     * Record a verified manual refund. This deliberately records the staff
     * action; it does not claim to initiate a transfer through GCash or a bank.
     */
    public function refundPayment(Request $request, Booking $booking)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot manage refunds.');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $refund = DB::transaction(function () use ($booking, $data, $request) {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $payments = Payment::where('booking_id', $lockedBooking->id)->lockForUpdate()->get();
            $paid = (float) $payments->where('status', 'paid')->sum('amount');
            $alreadyRefunded = (float) $payments->where('status', 'refunded')->sum('amount');
            $refundable = max(0.0, $paid - $alreadyRefunded);
            $amount = round((float) $data['amount'], 2);

            if ($lockedBooking->status !== 'cancelled') {
                throw ValidationException::withMessages(['amount' => 'Cancel the booking before recording a refund.']);
            }

            if (! $lockedBooking->isRefundEligible()) {
                throw ValidationException::withMessages(['amount' => 'The refund cutoff has passed. Refunds are available only when cancellation is at least 3 days before check-in.']);
            }

            if ($refundable < 0.01) {
                throw ValidationException::withMessages(['amount' => 'There is no recorded payment available to refund.']);
            }

            if ($amount > $refundable + 0.00001) {
                throw ValidationException::withMessages(['amount' => 'The refund cannot exceed the remaining refundable amount of ₱'.number_format($refundable, 2).'.']);
            }

            $before = $lockedBooking->only(['payment_status', 'payment_method', 'paid_at']);
            $fullyRefunded = abs($refundable - $amount) < 0.01;
            $refund = Payment::create([
                'booking_id' => $lockedBooking->id,
                'amount' => $amount,
                'method' => in_array($lockedBooking->payment_method, ['cash', 'gcash'], true) ? $lockedBooking->payment_method : 'cash',
                'status' => 'refunded',
                'reference' => 'REF-'.$lockedBooking->reference.'-'.now()->format('YmdHis'),
                'notes' => 'Refund reason: '.trim($data['reason']),
                'paid_at' => now(),
                'recorded_by' => $request->user()->id,
            ]);

            $lockedBooking->update(['payment_status' => $fullyRefunded ? 'refunded' : 'paid']);
            $this->log(
                $lockedBooking->fresh(),
                $request->user()->id,
                'payment_refunded',
                'Refund of ₱'.number_format($amount, 2).' recorded. Reason: '.trim($data['reason']),
                $before,
                ['refund_amount' => $amount, 'remaining_refundable' => max(0, $refundable - $amount), 'payment_status' => $fullyRefunded ? 'refunded' : 'paid']
            );

            return $refund;
        });

        $freshBooking = $booking->fresh('room');
        $this->email($freshBooking, 'Refund recorded for your Carolina booking', 'A refund of ₱'.number_format((float) $refund->amount, 2).' has been recorded for booking '.$freshBooking->reference.'. Please contact Carolina if you have any questions.');

        return back()->with('success', 'Refund of ₱'.number_format((float) $refund->amount, 2).' recorded and the guest was notified.');
    }

    public function walkInForm()
    {
        $this->refreshInventory();
        // Future reservations are handled by the date calendar. Only rooms that
        // are currently unavailable for operational reasons are excluded here.
        $rooms = Room::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        return view('admin.walk_in', compact('rooms'));
    }

    public function frontdesk()
    {
        $today = today();
        $tomorrow = $today->copy()->addDay();

        return view('admin.frontdesk', [
            'arrivals' => Booking::with(['user', 'room'])->where('status', 'confirmed')->whereDate('check_in', $today)->whereNull('checked_in_at')->get(),
            'activeStays' => Booking::with(['user', 'room'])->whereNotNull('checked_in_at')->whereNull('checked_out_at')->get(),
            'departures' => Booking::with(['user', 'room'])->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('check_out', '<=', $today)->get(),
            'roomOperationBlocks' => RoomBlock::with('room')->whereIn('status', ['cleaning', 'maintenance'])->overlapping($today, $tomorrow)->orderBy('starts_at')->get(),
        ]);
    }

    public function storeWalkIn(Request $request)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot create walk-ins.');
        $this->refreshInventory();
        $data = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email:rfc|max:255',
            'guest_phone' => 'required|string|max:30',
            'guests' => 'required|integer|min:1',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
        ]);

        $checkInAt = Carbon::parse($data['check_in'].' '.($data['check_in_time'] ?? '12:00'));
        $checkOutAt = Carbon::parse($data['check_out'].' '.($data['check_out_time'] ?? '12:00'));
        if ($checkOutAt->lte($checkInAt)) {
            return back()->withInput()->withErrors(['check_out_time' => 'Check-out must be after check-in.']);
        }
        $nights = max(1, (int) ceil($checkInAt->diffInHours($checkOutAt) / 24));
        $booking = DB::transaction(function () use ($data, $checkInAt, $checkOutAt, $nights) {
            $room = Room::whereKey($data['room_id'])->lockForUpdate()->firstOrFail();
            if ($data['guests'] > $room->guests) {
                throw ValidationException::withMessages(['guests' => "{$room->name} accommodates up to {$room->guests} guests."]);
            }
            $conflict = $room->bookings()->blocking()->overlapping($checkInAt, $checkOutAt)->exists()
                || $room->blocks()->overlapping($checkInAt, $checkOutAt)->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['room_id' => 'That room is unavailable for the selected stay.']);
            }

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

        if (! AdminPermissions::allows(request()->user(), 'bookings')) {
            return view('admin.room-board', compact('rooms'));
        }

        return view('admin.rooms', compact('rooms'));
    }

    public function createRoom(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can add rooms.');

        return view('admin.room-form', ['room' => new Room, 'action' => route('admin.rooms.store'), 'method' => 'POST']);
    }

    public function storeRoom(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can add rooms.');
        $room = Room::create($this->roomData($request));
        $this->audit($request->user()->id, 'room_created', 'Room added to the catalog.', $room, [], $room->only(['name', 'room_type', 'price_per_night', 'guests', 'is_active']));

        return redirect()->route('admin.rooms')->with('success', 'Room added to the catalog.');
    }

    public function editRoom(Request $request, Room $room)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can edit room details.');

        return view('admin.room-form', ['room' => $room, 'action' => route('admin.rooms.update', $room), 'method' => 'PATCH']);
    }

    public function updateRoom(Request $request, Room $room)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can edit room details.');
        $before = $room->only(['name', 'room_type', 'description', 'price_per_night', 'guests', 'beds', 'amenities', 'image_url', 'is_active']);
        $room->update($this->roomData($request, $room));
        $this->audit($request->user()->id, 'room_updated', 'Room details updated.', $room, $before, $room->fresh()->only(['name', 'room_type', 'description', 'price_per_night', 'guests', 'beds', 'amenities', 'image_url', 'is_active']));

        return redirect()->route('admin.rooms')->with('success', 'Room details updated.');
    }

    public function archiveRoom(Request $request, Room $room)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can archive rooms.');
        abort_if($room->bookings()->blocking()->where('check_out_at', '>', now())->exists(), 422, 'This room has future or active confirmed stays and cannot be archived yet.');
        $room->update(['is_active' => false]);
        $this->audit($request->user()->id, 'room_archived', 'Room archived without deleting booking history.', $room, ['is_active' => true], ['is_active' => false]);

        return back()->with('success', 'Room archived. Historical reservations were kept.');
    }

    public function bookings(Request $request)
    {
        $this->refreshInventory();
        $period = $request->string('period', 'daily')->value();
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly', 'yearly'], true), 404);

        [$chartLabels, $chartValues] = $this->bookingChartData($period);
        $rooms = $this->roomsWithDisplayStatus();

        $bookingQuery = Booking::with(['user', 'room', 'payments', 'timeAdjustments'])->latest();
        if ($request->filled('status')) {
            $bookingQuery->where('status', $request->string('status')->value());
        }
        if ($request->filled('arrival')) {
            $arrival = $request->string('arrival')->value();
            $bookingQuery->where(function ($query) use ($arrival) {
                $query->whereDate('check_in', $arrival)->orWhereDate('check_out', $arrival);
            });
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->value().'%';
            $bookingQuery->where(fn ($query) => $query->where('reference', 'like', $search)->orWhere('guest_name', 'like', $search)->orWhere('guest_email', 'like', $search));
        }

        $perPage = $request->integer('per_page', 5);
        if (! in_array($perPage, [5, 15, 25, 50, 100], true)) {
            $perPage = 5;
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
            'individualBookings' => $bookingQuery->paginate($perPage, ['*'], 'booking_page')->withQueryString(),
            'arrivalsToday' => Booking::with('room')->where('status', 'confirmed')->whereDate('check_in', today())->whereNull('checked_in_at')->where('check_in_at', '<=', now())->get(),
            'departuresToday' => Booking::with('room')->whereNotNull('checked_in_at')->whereNull('checked_out_at')->whereDate('check_out', '<=', today())->get(),
            'recentActivity' => ActivityLog::with(['booking.room', 'user'])->latest()->take(8)->get(),
            'pendingReviews' => Review::with(['booking.room', 'user'])->where('status', '!=', 'approved')->latest()->take(10)->get(),
            'recentReviews' => Review::with(['booking.room'])->whereIn('status', ['approved', 'hidden'])->latest()->take(8)->get(),
        ]);
    }

    public function showBooking(Request $request, Booking $booking)
    {
        $booking->load(['user', 'room', 'payments.recordedBy', 'activityLogs.user']);
        $financial = FinancialSummary::forBooking($booking);

        return view('admin.booking-show', compact('booking', 'financial'));
    }

    public function exportBookings(Request $request)
    {
        $query = Booking::with(['user', 'room'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }
        if ($request->filled('arrival')) {
            $query->whereDate('check_in', $request->string('arrival')->value());
        }
        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->value().'%';
            $query->where(fn ($builder) => $builder->where('reference', 'like', $search)->orWhere('guest_name', 'like', $search)->orWhere('guest_email', 'like', $search));
        }
        $filename = 'carolina-bookings-'.now()->format('Y-m-d').'.csv';
        $this->audit($request->user()->id, 'booking_exported', 'Booking CSV exported.', $request->user(), [], ['filters' => $request->only(['status', 'arrival', 'search'])]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Guest', 'Email', 'Phone', 'Room', 'Check-in', 'Check-out', 'Status', 'Payment status', 'Total']);
            $query->chunkById(200, function ($bookings) use ($out) {
                foreach ($bookings as $booking) {
                    fputcsv($out, array_map(fn ($value) => $this->csvValue($value), [
                        $booking->reference, $booking->guest_name ?? $booking->user?->name, $booking->guest_email ?? $booking->user?->email, $booking->guest_phone,
                        $booking->room?->name, $booking->check_in_at?->toIso8601String() ?? $booking->check_in->toDateString(), $booking->check_out_at?->toIso8601String() ?? $booking->check_out->toDateString(),
                        $booking->status, $booking->payment_status, $booking->total_amount,
                    ]));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function guests(Request $request)
    {
        $search = strtolower(trim((string) $request->input('search')));
        $groups = Booking::with('user')->latest()->get()->groupBy(fn (Booking $booking) => $this->guestKey($booking))->map(function ($bookings, $key) {
            $latest = $bookings->sortByDesc('created_at')->first();

            return (object) ['key' => $key, 'token' => $this->guestToken($key), 'name' => $latest->guest_name ?? $latest->user?->name ?? 'Guest', 'email' => $latest->guest_email ?? $latest->user?->email, 'phone' => $latest->guest_phone, 'bookings' => $bookings->count(), 'latest' => $latest];
        })->values();
        if ($search !== '') {
            $groups = $groups->filter(fn ($guest) => str_contains(strtolower($guest->name.' '.$guest->email.' '.$guest->phone), $search))->values();
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $guests = new LengthAwarePaginator($groups->forPage($page, $perPage)->values(), $groups->count(), $perPage, $page, ['path' => route('admin.guests'), 'query' => $request->query()]);

        return view('admin.guests', compact('guests'));
    }

    public function showGuest(Request $request, string $guest)
    {
        $key = $this->guestKeyFromToken($guest);
        $bookings = Booking::with(['user', 'room', 'payments'])->latest()->get()->filter(fn (Booking $booking) => $this->guestKey($booking) === $key)->values();
        abort_if($bookings->isEmpty(), 404);
        $latest = $bookings->first();
        $notes = GuestNote::with('author')->where('guest_key', $key)->latest()->get();
        $restriction = GuestRestriction::with('createdBy')->where('guest_key', $key)->whereNull('removed_at')->first();

        return view('admin.guest-show', ['guestKey' => $key, 'guestToken' => $guest, 'guest' => $latest, 'bookings' => $bookings, 'notes' => $notes, 'restriction' => $restriction]);
    }

    public function storeGuestNote(Request $request, string $guest)
    {
        $key = $this->guestKeyFromToken($guest);
        $data = $request->validate(['content' => 'required|string|max:2000']);
        $note = GuestNote::create(['guest_key' => $key, 'author_id' => $request->user()->id, 'content' => trim($data['content'])]);
        $this->audit($request->user()->id, 'guest_note_added', 'Guest note added.', $note, [], ['guest_key' => $key]);

        return back()->with('success', 'Guest note saved.');
    }

    public function updateGuestRestriction(Request $request, string $guest)
    {
        $key = $this->guestKeyFromToken($guest);
        $data = $request->validate(['action' => 'required|in:restrict,remove', 'reason' => 'nullable|string|max:500']);
        $restriction = GuestRestriction::firstOrNew(['guest_key' => $key]);
        if ($data['action'] === 'restrict') {
            $reason = trim((string) ($data['reason'] ?? ''));
            if ($reason === '') {
                return back()->withErrors(['reason' => 'Provide an internal reason for this booking restriction.']);
            }
            $restriction->fill(['reason' => $reason, 'created_by' => $request->user()->id, 'removed_at' => null, 'removed_by' => null])->save();
            $this->audit($request->user()->id, 'guest_restriction_added', 'Guest booking restriction added.', $restriction, [], ['guest_key' => $key]);

            return back()->with('success', 'Guest booking restriction added.');
        }
        if ($restriction->exists) {
            $restriction->update(['removed_at' => now(), 'removed_by' => $request->user()->id]);
            $this->audit($request->user()->id, 'guest_restriction_removed', 'Guest booking restriction removed.', $restriction, [], ['guest_key' => $key]);
        }

        return back()->with('success', 'Guest booking restriction removed.');
    }

    public function updateReview(Request $request, Review $review)
    {
        abort_unless($request->user()->canManageBookings(), 403, 'Your staff role cannot moderate reviews.');
        $approving = $request->input('status') === 'approved';
        $data = $request->validate([
            'status' => 'required|in:approved',
            'guest_name' => $approving ? 'required|string|max:255' : 'sometimes|required|string|max:255',
            'comment' => $approving ? 'present|nullable|string|max:750' : 'sometimes|nullable|string|max:750',
            'privacy_checked' => $approving ? 'accepted' : 'sometimes|accepted',
        ]);
        $review->update([
            'status' => $data['status'],
            'guest_name' => $approving
                ? trim($data['guest_name'])
                : $review->guest_name,
            'comment' => $approving
                ? (filled($data['comment']) ? trim($data['comment']) : null)
                : $review->comment,
            'approved_at' => $approving ? now() : null,
            'approved_by' => $approving ? $request->user()->id : null,
        ]);
        $this->log($review->booking, $request->user()->id, 'review_'.$data['status'], 'Guest review '.$data['status'].' by staff.');

        return back()->with('success', 'Review '.$data['status'].'.');
    }

    public function reports(Request $request)
    {
        $this->refreshInventory();
        $period = $request->string('period', 'daily')->value();
        $metric = $request->string('metric', 'payments')->value();
        if ($metric === 'earnings') {
            $metric = 'payments';
        } // retain old shared links without retaining misleading wording
        abort_unless(in_array($period, ['daily', 'weekly', 'monthly', 'yearly', 'custom'], true), 404);
        abort_unless(in_array($metric, ['payments', 'bookings', 'guests'], true), 404);

        try {
            $range = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        } catch (ValidationException $exception) {
            if ($period !== 'custom') {
                throw $exception;
            }

            return redirect()
                ->route('admin.reports', ['metric' => $metric, 'period' => 'monthly'])
                ->withInput($request->only(['from', 'to']))
                ->withErrors($exception->errors());
        }

        if ($period === 'custom') {
            $rangeErrors = [];
            if (empty($range['from'])) {
                $rangeErrors['from'] = 'Choose a start date for the custom report.';
            }
            if (empty($range['to'])) {
                $rangeErrors['to'] = 'Choose an end date for the custom report.';
            }

            if ($rangeErrors !== []) {
                return redirect()
                    ->route('admin.reports', ['metric' => $metric, 'period' => 'monthly'])
                    ->withInput($request->only(['from', 'to']))
                    ->withErrors($rangeErrors);
            }

            $from = Carbon::parse($range['from'])->startOfDay();
            $to = Carbon::parse($range['to'])->endOfDay();
            $lastAllowedDay = $from->copy()->addMonthsNoOverflow(3);
            if (Carbon::parse($range['to'])->startOfDay()->gt($lastAllowedDay)) {
                return redirect()
                    ->route('admin.reports', ['metric' => $metric, 'period' => 'monthly'])
                    ->withInput($request->only(['from', 'to']))
                    ->withErrors(['to' => 'Choose a custom report range of three months or less.']);
            }
            $dates = collect(CarbonPeriod::create($from, '1 day', $to))->map(fn (Carbon $date) => $date->copy());
            $keys = $dates->map(fn (Carbon $date) => $date->toDateString());
            $labels = $dates->map(fn (Carbon $date) => $date->format('M j'));
            $periodStart = $from;
            $periodEnd = $to;
            $bookings = Booking::with(['user', 'room', 'payments'])->whereBetween('created_at', [$periodStart, $periodEnd])->get();
            $grouped = $bookings->groupBy(fn (Booking $booking) => $booking->created_at->toDateString());
        } else {
            [$dates, $keys, $labels] = $this->reportPeriods($period);
            $periodStart = $dates->first();
            $periodEnd = now()->endOfDay();
            $bookings = Booking::with(['user', 'room', 'payments'])->whereBetween('created_at', [$periodStart, $periodEnd])->get();
            $grouped = $bookings->groupBy(fn (Booking $booking) => $this->reportKey($booking->created_at, $period));
        }
        $paymentStart = $periodStart;
        $paymentEnd = $periodEnd;
        $paymentGroups = Payment::whereIn('status', ['paid', 'refunded'])->whereBetween('paid_at', [$paymentStart, $paymentEnd])->get()
            ->groupBy(fn (Payment $payment) => $period === 'custom' ? $payment->paid_at->toDateString() : $this->reportKey($payment->paid_at, $period));
        $values = $keys->map(function (string $key) use ($grouped, $paymentGroups, $metric) {
            $items = $grouped->get($key, collect());

            return match ($metric) {
                'payments' => (float) $paymentGroups->get($key, collect())->sum(fn (Payment $payment) => $payment->status === 'refunded' ? -$payment->amount : $payment->amount),
                default => $items->count(),
            };
        });
        $titles = ['payments' => ['Payments collected', 'Net collected payments'], 'bookings' => ['Total bookings', 'Bookings'], 'guests' => ['Guest bookings', 'Bookings']];
        $guestGroups = $metric === 'guests' ? $bookings->groupBy(fn (Booking $booking) => $this->guestKey($booking)) : collect();
        $previousGuestKeys = $metric === 'guests'
            ? Booking::with('user')->where('created_at', '<', $periodStart)->get()->map(fn (Booking $booking) => $this->guestKey($booking))->unique()->flip()
            : collect();
        $durationHours = $bookings->map(function (Booking $booking): ?float {
            if ($booking->check_in_at && $booking->check_out_at) {
                return max(0, ($booking->check_out_at->getTimestamp() - $booking->check_in_at->getTimestamp()) / 3600);
            }

            if ($booking->booking_type === 'hourly' && (int) $booking->hours > 0) {
                return (float) $booking->hours;
            }

            return (int) $booking->nights > 0 ? (float) $booking->nights * 24 : null;
        })->filter(fn (?float $duration) => $duration !== null);

        return view('admin.reports', [
            'metric' => $metric,
            'period' => $period,
            'range' => $range,
            'metricTitle' => $titles[$metric][0],
            'chartTitle' => $titles[$metric][1],
            'total' => $values->sum(),
            'chartLabels' => $labels,
            'chartValues' => $values,
            'latestBookings' => $bookings->sortByDesc('created_at')->take(5)->values(),
            'bookingReportMetrics' => $metric === 'bookings' ? [
                'total' => $bookings->count(),
                'cancelled' => $bookings->where('status', 'cancelled')->count(),
                'roomsBooked' => $bookings->where('status', '!=', 'cancelled')->pluck('room_id')->unique()->count(),
                'completed' => Booking::whereNotNull('checked_out_at')->whereBetween('checked_out_at', [$periodStart, $periodEnd])->count(),
            ] : null,
            'guestReportMetrics' => $metric === 'guests' ? [
                'total' => $guestGroups->count(),
                'new' => $guestGroups->keys()->filter(fn (string $key) => ! $previousGuestKeys->has($key))->count(),
                'returning' => $guestGroups->keys()->filter(fn (string $key) => $previousGuestKeys->has($key))->count(),
                'averageDurationHours' => $durationHours->isNotEmpty() ? round((float) $durationHours->avg(), 1) : null,
            ] : null,
            'financialMetrics' => array_merge(
                FinancialSummary::forBookings($bookings),
                ['period_ledger' => FinancialSummary::forPayments($paymentGroups->flatten())]
            ),
        ]);
    }

    public function activity(Request $request)
    {
        return view('admin.activity', [
            'logs' => ActivityLog::with(['booking.room', 'user'])->latest()->paginate(30)->withQueryString(),
        ]);
    }

    public function staff(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can manage staff roles.');

        return view('admin.staff', ['staff' => User::orderByDesc('is_admin')->orderBy('name')->get()]);
    }

    public function updateStaffRole(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only Organization Admins can manage staff roles.');
        $role = $request->validate(['staff_role' => 'required|in:organization_admin,front_desk,housekeeping,viewer,guest'])['staff_role'];
        abort_if($user->id === $request->user()->id && $role !== 'organization_admin', 422, 'You cannot remove your own Organization Admin access.');
        $before = $user->only(['staff_role', 'is_admin']);
        $user->update(['staff_role' => $role, 'is_admin' => $role === 'organization_admin']);
        $this->audit($request->user()->id, 'staff_role_updated', 'Staff role updated.', $user, $before, $user->fresh()->only(['staff_role', 'is_admin']));

        return back()->with('success', $user->name."'s staff role was updated.");
    }

    public function updateRoomStatus(Request $request, Room $room)
    {
        abort_unless($request->user()->canManageRooms(), 403, 'Your staff role cannot manage room operations.');
        // Housekeeping uses one work date with a start and finish hour. Keep the
        // older separate-date fields working for the administrator room tools.
        if (! $request->filled('operational_starts_at') && $request->filled('operational_date') && $request->filled('operational_start_time')) {
            $request->merge(['operational_starts_at' => $request->input('operational_date').' '.$request->input('operational_start_time')]);
        }
        if (! $request->filled('operational_until') && $request->filled('operational_date') && $request->filled('operational_end_time')) {
            $request->merge(['operational_until' => $request->input('operational_date').' '.$request->input('operational_end_time')]);
        }
        if (! $request->filled('operational_starts_at') && $request->filled('operational_start_date') && $request->filled('operational_start_time')) {
            $request->merge(['operational_starts_at' => $request->input('operational_start_date').' '.$request->input('operational_start_time')]);
        }
        if (! $request->filled('operational_until') && $request->filled('operational_end_date') && $request->filled('operational_end_time')) {
            $request->merge(['operational_until' => $request->input('operational_end_date').' '.$request->input('operational_end_time')]);
        }
        $data = $request->validate([
            'operational_status' => 'required|in:available,cleaning,maintenance',
            'operational_starts_at' => 'nullable|date',
            'operational_until' => 'nullable|date|after:operational_starts_at',
            'operational_date' => 'nullable|date',
            'operational_start_date' => 'nullable|date',
            'operational_end_date' => 'nullable|date',
            'operational_start_time' => 'nullable|date_format:H:i',
            'operational_end_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:255',
            'force_override' => 'nullable|boolean',
        ]);
        if ($data['operational_status'] === 'available') {
            $propertyNow = PropertyTime::now();
            $room->blocks()->where('ends_at', '>', $propertyNow)->where('starts_at', '<=', $propertyNow)->delete();
            $this->audit($request->user()->id, 'room_block_cleared', 'Active room block cleared.', $room);

            return back()->with('success', 'Active room blocks cleared.');
        }
        if (empty($data['operational_starts_at']) || empty($data['operational_until'])) {
            return back()->withErrors(['operational_until' => 'Set both a start and end time for this room block.']);
        }
        $startsAt = Carbon::parse($data['operational_starts_at']);
        $endsAt = Carbon::parse($data['operational_until']);
        $override = (bool) ($data['force_override'] ?? false);
        $notes = $data['notes'] ?? null;
        $overlap = DB::transaction(function () use ($room, $startsAt, $endsAt, $override, $notes, $data, $request) {
            $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $overlap = $lockedRoom->bookings()->blocking()->overlapping($startsAt, $endsAt)->exists();

            if ($overlap && (! $override || ! $request->user()->isAdmin())) {
                throw ValidationException::withMessages(['operational_until' => 'This operation overlaps a confirmed stay. Choose another time, or have an Organization Admin record an override.']);
            }

            $existingBlock = $lockedRoom->blocks()->overlapping($startsAt, $endsAt)->orderBy('starts_at')->first();
            if ($existingBlock) {
                $existingStatus = ucfirst($existingBlock->status);
                $existingStart = $existingBlock->starts_at->format('M j, g:i A');
                $existingEnd = $existingBlock->ends_at->format('M j, g:i A');

                throw ValidationException::withMessages(['operational_until' => "Room {$lockedRoom->name} already has {$existingStatus} scheduled from {$existingStart} to {$existingEnd}. Choose a time outside that window."]);
            }

            $blockNotes = $overlap && $override
                ? trim(($notes ? $notes.' · ' : '').'Manager override by '.$request->user()->name)
                : $notes;

            RoomBlock::create(['room_id' => $lockedRoom->id, 'status' => $data['operational_status'], 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'notes' => $blockNotes, 'created_by' => $request->user()->id]);

            return $overlap;
        });
        $this->audit($request->user()->id, 'room_block_created', ucfirst($data['operational_status']).' block scheduled.', $room, [], ['status' => $data['operational_status'], 'starts_at' => $startsAt->toDateTimeString(), 'ends_at' => $endsAt->toDateTimeString()]);

        return back()->with('success', $overlap ? 'Scheduled room block saved with manager override.' : 'Scheduled room block saved.');
    }

    private function roomData(Request $request, ?Room $room = null): array
    {
        $slugRule = ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('rooms', 'slug')->ignore($room?->id)];
        $data = $request->validate([
            'name' => 'required|string|max:255', 'slug' => $slugRule, 'room_type' => 'required|string|max:100',
            'description' => 'required|string|max:3000', 'beds' => 'required|integer|min:1|max:20', 'guests' => 'required|integer|min:1|max:30',
            'price_per_night' => 'required|numeric|min:0|max:999999', 'rental_hours' => 'nullable|integer|in:3,6,12,22,24', 'default_check_in_time' => 'nullable|date_format:H:i',
            'amenities' => 'nullable|string|max:1200', 'image_url' => 'nullable|url|max:2048', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active' => 'nullable|boolean', 'remove_image' => 'nullable|boolean',
        ]);
        if ($request->boolean('remove_image')) {
            $data['image_url'] = null;
        }
        if ($request->hasFile('image')) {
            if ($room?->image_url && Str::startsWith($room->image_url, 'room-images/')) {
                Storage::disk('public')->delete($room->image_url);
            }
            $data['image_url'] = $request->file('image')->store('room-images', 'public');
        }
        $data['amenities'] = collect(explode(',', (string) ($data['amenities'] ?? '')))->map(fn ($item) => trim($item))->filter()->values()->all();
        $data['is_active'] = $request->boolean('is_active');
        unset($data['image'], $data['remove_image']);

        return $data;
    }

    private function roomsWithDisplayStatus()
    {
        $now = PropertyTime::now();
        $today = $now->copy()->startOfDay();

        return Room::where('is_active', true)->with(['bookings' => fn ($query) => $query
            ->with('user')
            ->blocking()
            ->where('check_out', '>=', $today->toDateString())
            ->orderBy('check_in'), 'blocks' => fn ($query) => $query->whereNull('completed_at')
                ->where(fn ($active) => $active->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', $now))
                ->where('ends_at', '>', $now)->orderBy('starts_at')])
            ->orderBy('id')
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
        $now = PropertyTime::now();
        RoomBlock::whereNull('completed_at')->where('ends_at', '<=', $now)->update(['completed_at' => $now]);
    }

    /**
     * The payment ledger was introduced after live reservations already
     * existed. A dashboard should never become unavailable while an older
     * database is catching up, so retain the legacy booking fields as a
     * read-only fallback for its summary cards.
     */
    private function paymentDashboardMetrics(): array
    {
        try {
            $ledger = FinancialSummary::forPayments(Payment::whereIn('status', ['paid', 'refunded'])->get());

            return [
                $ledger['net_collected'],
                Booking::where('status', '!=', 'cancelled')
                    ->whereDoesntHave('payments', fn ($query) => $query->where('status', 'paid'))
                    ->count(),
                'Verified payment ledger, net of refunds',
            ];
        } catch (QueryException $exception) {
            Log::warning('Payment ledger unavailable for dashboard; using legacy payment fields.', [
                'exception' => $exception,
            ]);

            return [
                Booking::where('payment_status', 'paid')->sum('total_amount'),
                Booking::where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->count(),
                'Legacy booking payment records (ledger unavailable)',
            ];
        }
    }

    private function log(Booking $booking, ?int $userId, string $event, string $description, array $before = [], array $after = []): void
    {
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => $userId, 'subject_type' => $booking::class, 'subject_id' => $booking->id, 'event' => $event, 'description' => $description, 'before_values' => $before ?: null, 'after_values' => $after ?: null]);
    }

    private function audit(?int $userId, string $event, string $description, mixed $subject, array $before = [], array $after = []): void
    {
        ActivityLog::create([
            'booking_id' => $subject instanceof Booking ? $subject->id : null,
            'user_id' => $userId,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'event' => $event,
            'description' => $description,
            'before_values' => $before ?: null,
            'after_values' => $after ?: null,
        ]);
    }

    private function email(Booking $booking, string $subject, string $message): void
    {
        $email = $booking->guest_email ?? $booking->user?->email;
        if (! $email) {
            return;
        }
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

        return [$dates->map(fn (Carbon $date) => $period === 'weekly' ? 'Week '.$date->weekOfYear : $date->format($format)), $keys->map(fn (string $key) => $grouped->get($key, collect())->count())];
    }

    private function reportPeriods(string $period): array
    {
        $now = now();
        $configuration = match ($period) {
            'weekly' => [4, fn (int $index) => $now->copy()->subWeeks(3 - $index)->startOfWeek(), fn (Carbon $date) => 'Week '.$date->weekOfYear],
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
        $email = strtolower(trim((string) ($booking->guest_email ?? $booking->user?->email)));
        if ($email !== '') {
            return 'email:'.$email;
        }
        $phone = preg_replace('/\D+/', '', (string) $booking->guest_phone);

        return $phone !== '' ? 'phone:'.$phone : 'booking:'.$booking->id;
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }

    private function guestToken(string $key): string
    {
        return rtrim(strtr(base64_encode($key), '+/', '-_'), '=');
    }

    private function guestKeyFromToken(string $token): string
    {
        $key = base64_decode(strtr($token, '-_', '+/'), true);
        abort_unless($key && preg_match('/^(email|phone|booking):/', $key), 404);

        return $key;
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
