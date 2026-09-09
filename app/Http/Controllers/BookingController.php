<?php

namespace App\Http\Controllers;

use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Crypt;

class BookingController extends Controller
{
    public function create(Request $request, Room $room)
    {
        if (! $room->is_active || $room->operational_status !== 'available') {
            return redirect()->route('rooms.index')->withErrors(['room' => "{$room->name} is currently {$room->operational_status} and cannot be booked right now."]);
        }
        $blockedRanges = $this->blockedRanges($room);

        return view('bookings.create', [
            'room' => $room,
            'isGuest' => $request->boolean('guest'),
            'bookingMode' => $room->rental_hours ? 'hourly' : $request->string('mode', 'dates')->value(),
            'blockedRanges' => $blockedRanges,
        ]);
    }

    public function availability(Room $room)
    {
        return response()->json(['ranges' => $this->blockedRanges($room)->values()]);
    }

    private function blockedRanges(Room $room)
    {
        $this->expireUnpaidBookings($room);
        return $room->bookings()
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('check_out', '>', now()->startOfDay())
            ->orderBy('check_in')
            ->get(['check_in', 'check_out'])
            ->map(fn (Booking $booking) => [
                'start' => $booking->check_in->toDateString(),
                'end' => $booking->check_out->toDateString(),
            ]);

    }

    public function store(Request $request, Room $room)
    {
        $this->expireUnpaidBookings($room);
        if (! $room->is_active || $room->operational_status !== 'available') {
            return redirect()->route('rooms.index')->withErrors(['room' => "{$room->name} is currently {$room->operational_status} and cannot be booked right now."]);
        }
        if ($request->filled('guest_phone')) {
            $request->merge(['guest_phone' => preg_replace('/[\s()\-]/', '', (string) $request->input('guest_phone'))]);
        }
        $rules = ['booking_type' => 'required|in:dates,hourly', 'guests' => 'required|integer|min:1|max:' . $room->guests, 'payment_method' => 'required|in:gcash,cash', 'special_request' => 'nullable|string|max:500', 'checkout_type' => 'required|in:guest,account'];
        if ($request->input('booking_type') === 'hourly') $rules += ['hourly_date' => 'required|date|after_or_equal:today', 'check_in_time' => 'required|date_format:H:i', 'hours' => 'required|integer|in:' . ($room->rental_hours ?: '3,12,24')];
        else $rules += ['check_in' => 'required|date|after_or_equal:today', 'check_out' => 'required|date|after:check_in'];
        if ($request->input('checkout_type') === 'guest') $rules += ['guest_name' => 'required|string|max:255', 'guest_email' => 'required|email:rfc,dns|max:255', 'guest_phone' => ['required', 'regex:/^(?:\\+63|63|0)9\\d{9}$/'], 'billing_street' => 'required|string|max:255', 'billing_city' => 'required|string|max:100', 'billing_province' => 'required|string|max:100', 'billing_postal_code' => 'required|regex:/^\\d{4}$/', 'billing_verified' => 'accepted'];
        $data = $request->validate($rules);
        if ($data['checkout_type'] === 'account' && ! $request->user()) return redirect()->route('login')->with('success', 'Please sign in to use account checkout.');
        if ($data['booking_type'] === 'hourly') {
            $checkInAt = Carbon::parse($data['hourly_date'] . ' ' . $data['check_in_time']);
            $checkOutAt = $checkInAt->copy()->addHours($data['hours']);
            $hourlyEndDate = $checkOutAt->isStartOfDay() ? $checkOutAt->toDateString() : $checkOutAt->copy()->addDay()->toDateString();
            $taken = $room->bookings()->whereIn('status', ['pending', 'confirmed'])->where(function ($query) use ($checkInAt, $checkOutAt, $hourlyEndDate) { $query->where(fn ($hourly) => $hourly->whereNotNull('check_in_at')->where('check_in_at', '<', $checkOutAt)->where('check_out_at', '>', $checkInAt))->orWhere(fn ($dates) => $dates->whereNull('check_in_at')->where('check_in', '<', $hourlyEndDate)->where('check_out', '>', $checkInAt->toDateString())); })->exists();
            $nights = 1; $total = $room->rental_hours ? (float) $room->price_per_night : round(($room->price_per_night / 24) * $data['hours'], 2);
            $data['check_in'] = $checkInAt->toDateString(); $data['check_out'] = $checkOutAt->toDateString(); $data['check_in_at'] = $checkInAt; $data['check_out_at'] = $checkOutAt;
        } else { $taken = $room->bookings()->whereIn('status', ['pending', 'confirmed'])->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in'])->exists(); $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out'])); $total = $nights * $room->price_per_night; }
        if ($taken) return back()->withInput()->withErrors(['check_in' => 'Those dates are no longer available for this room.']);
        $guestData = $data['checkout_type'] === 'guest' ? ['guest_name' => $data['guest_name'], 'guest_email' => $data['guest_email'], 'guest_phone' => $data['guest_phone'], 'billing_street' => $data['billing_street'], 'billing_city' => $data['billing_city'], 'billing_province' => $data['billing_province'], 'billing_postal_code' => $data['billing_postal_code'], 'billing_verified_at' => now()] : [];
        $deposit = min($total, max((float) config('payments.minimum_deposit'), round($total * ((float) config('payments.deposit_percentage') / 100), 2)));
        $booking = Booking::create($data + $guestData + ['user_id' => $request->user()?->id, 'room_id' => $room->id, 'nights' => $nights, 'total_amount' => $total, 'deposit_amount' => $deposit, 'deposit_status' => 'awaiting_deposit', 'deposit_due_at' => now()->addMinutes(30), 'status' => 'pending']);
        $request->session()->put('guest_booking_reference', $booking->reference);
        $email = $booking->guest_email ?? $request->user()?->email;
        if ($email) Mail::to($email)->send(new BookingConfirmation($booking));
        return redirect()->route('bookings.receipt', $booking);
    }

    public function index(Request $request) { return view('bookings.index', ['bookings' => $request->user()->bookings()->with('room')->latest()->get()]); }
    public function receipt(Request $request, Booking $booking) { $this->authorizeBookingAccess($request, $booking); return view('bookings.receipt', compact('booking')); }
    public function confirmation(Request $request, Booking $booking) { $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id; $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference; abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403); return view('bookings.confirmation', compact('booking')); }
    public function submitDeposit(Request $request, Booking $booking)
    {
        $this->authorizeBookingAccess($request, $booking);
        abort_if($booking->status === 'cancelled', 422, 'This booking has been cancelled.');
        if ($booking->deposit_status === 'awaiting_deposit' && $booking->deposit_due_at?->isPast()) {
            $booking->update(['deposit_status' => 'expired', 'status' => 'cancelled']);
            abort(422, 'The 30-minute deposit window has expired and this room is available again.');
        }

        $data = $request->validate([
            'payment_reference' => 'required|string|min:4|max:100',
            'payment_proof' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $booking->update([
            'payment_reference' => trim($data['payment_reference']),
            'payment_proof_data' => Crypt::encryptString(base64_encode($request->file('payment_proof')->get())),
            'payment_proof_mime' => $request->file('payment_proof')->getMimeType(),
            'deposit_status' => 'submitted',
            'deposit_submitted_at' => now(),
        ]);
        $request->session()->put('guest_booking_reference', $booking->reference);

        return redirect()->route('bookings.receipt', $booking)->with('success', 'Deposit proof submitted. Carolina will verify it before confirming your booking.');
    }

    private function expireUnpaidBookings(Room $room): void
    {
        $room->bookings()
            ->where('status', 'pending')
            ->where('deposit_status', 'awaiting_deposit')
            ->whereNotNull('deposit_due_at')
            ->where('deposit_due_at', '<=', now())
            ->update(['status' => 'cancelled', 'deposit_status' => 'expired']);
    }
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
    public function lookupForm() { return view('bookings.lookup'); }
    public function lookup(Request $request) { $data = $request->validate(['email' => 'required|email', 'reference' => 'required|string']); $booking = Booking::with('room')->where('reference', strtoupper(trim($data['reference'])))->where(function ($query) use ($data) { $query->where('guest_email', $data['email'])->orWhereHas('user', fn ($user) => $user->where('email', $data['email'])); })->first(); if (! $booking) return back()->withErrors(['reference' => 'No booking matches that email and reference code.']); return view('bookings.lookup-result', ['booking' => $booking, 'lookupEmail' => $data['email']]); }

    private function cancelBeforeCheckIn(Booking $booking): void
    {
        abort_if($booking->status === 'cancelled', 422, 'This booking has already been cancelled.');
        abort_if($booking->check_in->isToday() || $booking->check_in->isPast(), 422, 'This reservation can no longer be cancelled online after check-in day begins.');
        $booking->update(['status' => 'cancelled']);
    }

    private function authorizeBookingAccess(Request $request, Booking $booking): void
    {
        $isOwner = $booking->user_id && $request->user() && $booking->user_id === $request->user()->id;
        $isGuestSession = $booking->user_id === null && $request->session()->get('guest_booking_reference') === $booking->reference;
        abort_unless($isOwner || $isGuestSession || $request->user()?->is_admin, 403);
    }
}
