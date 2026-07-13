<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function create(Room $room) { return view('bookings.create', compact('room')); }

    public function store(Request $request, Room $room)
    {
        $data = $request->validate(['check_in' => 'required|date|after_or_equal:today', 'check_out' => 'required|date|after:check_in', 'guests' => 'required|integer|min:1|max:' . $room->guests, 'payment_method' => 'required|in:gcash,card,cash', 'special_request' => 'nullable|string|max:500']);
        $taken = $room->bookings()->whereIn('status', ['pending', 'confirmed'])->where('check_in', '<', $data['check_out'])->where('check_out', '>', $data['check_in'])->exists();
        if ($taken) return back()->withInput()->withErrors(['check_in' => 'Those dates are no longer available for this room.']);
        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        $booking = Booking::create($data + ['user_id' => $request->user()->id, 'room_id' => $room->id, 'nights' => $nights, 'total_amount' => $nights * $room->price_per_night, 'status' => 'pending']);
        return redirect()->route('bookings.confirmation', $booking);
    }

    public function index(Request $request) { return view('bookings.index', ['bookings' => $request->user()->bookings()->with('room')->latest()->get()]); }
    public function confirmation(Request $request, Booking $booking) { abort_unless($booking->user_id === $request->user()->id || $request->user()->is_admin, 403); return view('bookings.confirmation', compact('booking')); }
    public function cancel(Request $request, Booking $booking) { abort_unless($booking->user_id === $request->user()->id, 403); abort_if($booking->check_in->isToday() || $booking->check_in->isPast(), 422, 'This reservation can no longer be cancelled online.'); $booking->update(['status' => 'cancelled']); return back()->with('success', 'Your booking has been cancelled.'); }
}
