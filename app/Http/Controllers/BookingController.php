<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmation;
use App\Mail\BookingUpdate;
use App\Mail\NewBookingRequest;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use App\Models\GuestRestriction;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class BookingController extends Controller
{
    public function create(Request $request, Room $room)
    {
        if (! $room->is_active) abort(404);
        $blockedRanges = $this->blockedRanges($room);

        $key = 'booking-submission:' . $room->id;
        $submissionToken = $request->session()->get($key) ?: (string) Str::uuid();
        $request->session()->put($key, $submissionToken);
        return view('bookings.create', [
            'room' => $room,
            'isGuest' => $request->boolean('guest'),
            'bookingMode' => $room->rental_hours ? 'hourly' : $request->string('mode', 'dates')->value(),
            'blockedRanges' => $blockedRanges,
            'hourlyBlockedSlots' => $this->hourlyBlockedSlots($room),
            'submissionToken' => $submissionToken,
        ]);
    }

    public function availability(Room $room)
    {
        return response()->json(['ranges' => $this->blockedRanges($room)->values(), 'slots' => $this->hourlyBlockedSlots($room)->values()]);
    }

    private function blockedRanges(Room $room)
    {
        $ranges = $room->bookings()
            ->blocking()
            ->where('check_out', '>', now()->startOfDay())
            ->orderBy('check_in')
            ->get(['check_in', 'check_out'])
            ->map(fn (Booking $booking) => [
                'start' => $booking->check_in->toDateString(),
                'end' => $booking->check_out->toDateString(),
            ]);
        $room->blocks()->where('ends_at', '>', now())->get()->each(fn (RoomBlock $block) => $ranges->push(['start' => $block->starts_at->toDateString(), 'end' => $block->ends_at->copy()->addDay()->toDateString()]));
        return $ranges;

    }

    private function hourlyBlockedSlots(Room $room)
    {
        $slots = $room->bookings()
            ->blocking()
            ->where('check_out', '>', now()->startOfDay())
            ->orderBy('check_in')
            ->get()
            ->map(function (Booking $booking) {
                $start = $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();
                $end = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();

                return ['start' => $start->toIso8601String(), 'end' => $end->toIso8601String()];
            });
        $room->blocks()->where('ends_at', '>', now())->get()->each(fn (RoomBlock $block) => $slots->push(['start' => $block->starts_at->toIso8601String(), 'end' => $block->ends_at->toIso8601String()]));
        return $slots;
    }

    public function store(Request $request, Room $room)
    {
        if (! $room->is_active) abort(404);
        if ($request->filled('guest_phone')) {
            $request->merge(['guest_phone' => preg_replace('/[\s()\-]/', '', (string) $request->input('guest_phone'))]);
        }
        $rules = ['booking_type' => 'required|in:dates,hourly', 'guests' => 'required|integer|min:1|max:' . $room->guests, 'children_count' => 'nullable|integer|min:0|max:10|lte:guests', 'pets_count' => 'nullable|integer|min:0|max:5', 'special_request' => 'nullable|string|max:500', 'checkout_type' => 'required|in:guest,account', 'submission_token' => 'required|uuid', 'terms_accepted' => 'accepted'];
        if ($request->input('booking_type') === 'hourly') $rules += ['hourly_date' => 'required|date|after_or_equal:today', 'check_in_time' => ['required', 'date_format:H:i', 'regex:/^(0[6-9]|1[0-9]|2[0-3]):00$/'], 'hours' => 'required|integer|in:' . ($room->rental_hours ? implode(',', array_unique([$room->rental_hours, 48, 72, 96, 120, 168])) : '3,12,24,48,72,96,120,168')];
        else $rules += ['check_in' => 'required|date|after_or_equal:today', 'check_out' => 'required|date|after:check_in'];
        if ($request->input('checkout_type') === 'guest') $rules += ['guest_name' => 'required|string|max:255', 'guest_email' => 'required|email:rfc|max:255', 'guest_phone' => ['required', 'regex:/^(?:\\+63|63|0)9\\d{9}$/'], 'terms_accepted' => 'accepted'];
        $data = $request->validate($rules);
        $restrictionEmail = $data['checkout_type'] === 'guest' ? ($data['guest_email'] ?? null) : $request->user()?->email;
        $restrictionPhone = $data['checkout_type'] === 'guest' ? ($data['guest_phone'] ?? null) : null;
        $guestKey = $restrictionEmail ? 'email:' . strtolower(trim($restrictionEmail)) : 'phone:' . preg_replace('/\D+/', '', (string) $restrictionPhone);
        abort_if(GuestRestriction::where('guest_key', $guestKey)->whereNull('removed_at')->exists(), 422, 'This reservation cannot be accepted online. Please contact Carolina directly.');
        $existing = Booking::where('submission_token', $data['submission_token'])->first();
        if ($existing) return redirect()->route('bookings.receipt', $existing)->with('success', 'Your reservation request was already received.');
        if ($data['checkout_type'] === 'account' && ! $request->user()) return redirect()->route('login')->with('success', 'Please sign in to use account checkout.');
        if ($data['booking_type'] === 'hourly') {
            $checkInAt = Carbon::parse($data['hourly_date'] . ' ' . $data['check_in_time']);
            $checkOutAt = $checkInAt->copy()->addHours((int) $data['hours']);
            $nights = max(1, (int) ceil($data['hours'] / 24));
            $total = $room->rental_hours
                ? round($room->price_per_night * match ((int) $data['hours']) { 48 => 2, 72 => 3, 96 => 4, 120 => 5, 168 => 7, default => 1 }, 2)
                : round(($room->price_per_night / 24) * $data['hours'], 2);
            $data['check_in'] = $checkInAt->toDateString(); $data['check_out'] = $checkOutAt->toDateString(); $data['check_in_at'] = $checkInAt; $data['check_out_at'] = $checkOutAt;
        } else { $checkInAt = Carbon::parse($data['check_in'])->startOfDay(); $checkOutAt = Carbon::parse($data['check_out'])->startOfDay(); $nights = $checkInAt->diffInDays($checkOutAt); $total = $nights * $room->price_per_night; $data['check_in_at'] = $checkInAt; $data['check_out_at'] = $checkOutAt; }
        $guestData = $data['checkout_type'] === 'guest' ? ['guest_name' => $data['guest_name'], 'guest_email' => $data['guest_email'], 'guest_phone' => $data['guest_phone']] : [];
        try {
        $booking = DB::transaction(function () use ($room, $data, $guestData, $request, $nights, $total, $checkInAt, $checkOutAt) {
            $lockedRoom = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_if(! $lockedRoom->is_active, 422, 'This room is unavailable.');
            $taken = $lockedRoom->bookings()->blocking()->overlapping($checkInAt, $checkOutAt)->exists()
                || $lockedRoom->blocks()->overlapping($checkInAt, $checkOutAt)->exists();
            abort_if($taken, 422, 'Those dates or hours are no longer available for this room.');
            return Booking::create($data + $guestData + ['user_id' => $request->user()?->id, 'room_id' => $lockedRoom->id, 'nights' => $nights, 'total_amount' => $total, 'add_ons' => [], 'hold_expires_at' => null, 'payment_method' => 'cash', 'status' => 'pending']);
        });
        } catch (QueryException $exception) {
            $booking = Booking::where('submission_token', $data['submission_token'])->first();
            if (! $booking) throw $exception;
            return redirect()->route('bookings.receipt', $booking)->with('success', 'Your reservation request was already received.');
        }
        $request->session()->forget('booking-submission:' . $room->id);
        try {
            $this->log($booking, $request->user()?->id, 'booking_created', 'Booking created and awaiting staff confirmation.');
        } catch (\Throwable $exception) {
            report($exception);
        }
        $request->session()->put('guest_booking_reference', $booking->reference);
        $email = $booking->guest_email ?? $request->user()?->email;
        if ($email) {
            try {
                Mail::to($email)->send(new BookingConfirmation($booking));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        $staffEmail = config('mail.notifications.address');
        if (filter_var($staffEmail, FILTER_VALIDATE_EMAIL) && strcasecmp((string) $staffEmail, (string) $email) !== 0) {
            try {
                Mail::to($staffEmail)->send(new NewBookingRequest($booking));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        return redirect()->route('bookings.receipt', $booking)->with('success', 'Reservation request recorded. Carolina will confirm availability before your stay is final.');
    }

    public function index(Request $request) { return view('bookings.index', ['bookings' => $request->user()->bookings()->with(['room', 'review'])->latest()->get()]); }

    public function submitReview(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);
        abort_if($booking->status === 'cancelled' || ! $booking->checked_out_at, 422, 'Reviews are available after check-out.');
        abort_if($booking->review()->exists(), 422, 'You have already reviewed this stay.');

        $data = $request->validate([
            'rating' => 'required|integer|between:1,5',
            'cleanliness_rating' => 'nullable|integer|between:1,5',
            'comfort_rating' => 'nullable|integer|between:1,5',
            'value_rating' => 'nullable|integer|between:1,5',
            'comment' => 'nullable|string|max:750',
        ]);

        Review::create($data + [
            'booking_id' => $booking->id,
            'room_id' => $booking->room_id,
            'user_id' => $request->user()->id,
            'guest_name' => $request->user()->name,
            'status' => 'pending',
        ]);
        $this->log($booking, $request->user()->id, 'review_submitted', 'Guest submitted a review for staff approval.');

        return back()->with('success', 'Thank you. Your review was submitted for approval.');
    }
    public function receipt(Request $request, Booking $booking) { $this->authorizeBookingAccess($request, $booking); return view('bookings.receipt', compact('booking')); }
    public function confirmation(Request $request, Booking $booking) { $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id; $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference; abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403); return view('bookings.confirmation', compact('booking')); }
    public function cancel(Request $request, Booking $booking)
    {
        abort_unless($booking->user_id === $request->user()?->id, 403);
        $this->cancelBeforeCheckIn($booking);

        return back()->with('success', 'Your booking has been cancelled. The room is available for those dates again.');
    }

    public function cancelGuest(Request $request, Booking $booking)
    {
        $data = $request->validate(['email' => 'required|email', 'reference' => 'required|string']);
        abort_unless(
            $booking->user_id === null
                && strcasecmp($booking->reference, trim($data['reference'])) === 0
                && strcasecmp((string) $booking->guest_email, trim($data['email'])) === 0,
            403
        );

        $this->cancelBeforeCheckIn($booking);
        session()->flash('success', 'Your booking has been cancelled. The room is available for those dates again.');

        return view('bookings.lookup-result', ['booking' => $booking->fresh('room'), 'lookupEmail' => $data['email']]);
    }
    public function extendGuest(Request $request, Booking $booking)
    {
        $data = $request->validate(['email' => 'required|email', 'reference' => 'required|string', 'hours' => 'required|integer|in:6,12,22,24,48,72,96,120,168']);
        abort_unless($booking->user_id === null && strcasecmp($booking->reference, trim($data['reference'])) === 0 && strcasecmp((string) $booking->guest_email, trim($data['email'])) === 0, 403);
        abort_if($booking->status !== 'confirmed' || ! $booking->checked_in_at || $booking->checked_out_at, 422, 'Extensions are available only for checked-in, confirmed guests.');
        $end = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();
        $newEnd = $end->copy()->addHours((int) $data['hours']);
        $conflict = Booking::where('room_id', $booking->room_id)->whereKeyNot($booking->id)->blocking()->overlapping($end, $newEnd)->exists() || RoomBlock::where('room_id', $booking->room_id)->overlapping($end, $newEnd)->exists();
        if ($conflict) return back()->withErrors(['hours' => 'The room is not available for that extension length.']);
        $baseHours = $booking->room->rental_hours ?: 24;
        $extra = round($booking->room->price_per_night * ($data['hours'] / $baseHours), 2);
        $booking->update(['check_out_at' => $newEnd, 'check_out' => $newEnd->toDateString(), 'hours' => ($booking->hours ?? 0) + $data['hours'], 'nights' => max(1, (int) ceil(($booking->hours + $data['hours']) / 24)), 'total_amount' => $booking->total_amount + $extra]);
        $this->log($booking, null, 'stay_extended', "Guest extended the stay by {$data['hours']} hours.");
        $this->emailUpdate($booking->fresh('room'), 'Your Carolina stay was extended', "Your stay was extended by {$data['hours']} hours. The updated total is included below.");
        return back()->with('success', 'Your stay was extended. Your updated receipt total is now available.');
    }
    public function lookupForm() { return view('bookings.lookup'); }
    public function lookup(Request $request) { $data = $request->validate(['email' => 'required|email', 'reference' => 'required|string']); $booking = Booking::with('room')->where('reference', strtoupper(trim($data['reference'])))->where(function ($query) use ($data) { $query->where('guest_email', $data['email'])->orWhereHas('user', fn ($user) => $user->where('email', $data['email'])); })->first(); if (! $booking) return back()->withErrors(['reference' => 'No booking matches that email and reference code.']); $request->session()->put('guest_booking_reference', $booking->reference); return view('bookings.lookup-result', ['booking' => $booking, 'lookupEmail' => $data['email']]); }

    private function cancelBeforeCheckIn(Booking $booking): void
    {
        abort_if($booking->status === 'cancelled', 422, 'This booking has already been cancelled.');
        abort_if($booking->check_in->isToday() || $booking->check_in->isPast(), 422, 'This reservation can no longer be cancelled online after check-in day begins.');
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Cancelled by guest.']);
        $this->log($booking, null, 'booking_cancelled', 'Booking cancelled by guest.');
        $this->emailUpdate($booking, 'Your Carolina booking was cancelled', 'Your reservation has been cancelled and the room is available again.');
    }

    private function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id;
        $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference;
        abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403);
    }

    private function log(Booking $booking, ?int $userId, string $event, string $description): void
    {
        ActivityLog::create(['booking_id' => $booking->id, 'user_id' => $userId, 'event' => $event, 'description' => $description]);
    }

    private function emailUpdate(Booking $booking, string $subject, string $message): void
    {
        $email = $booking->guest_email ?? $booking->user?->email;
        if (! $email) return;
        try {
            Mail::to($email)->send(new BookingUpdate($booking, $subject, $message));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
