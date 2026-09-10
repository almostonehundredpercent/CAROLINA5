<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::where('is_active', true)
            ->withAvg('approvedReviews', 'rating')->withCount('approvedReviews');
        $checkIn = $request->date('check_in');
        $checkOut = $request->date('check_out');
        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $startsAt = $checkIn->copy()->startOfDay();
            $endsAt = $checkOut->copy()->startOfDay();
            $rooms->whereDoesntHave('bookings', fn ($query) => $query->blocking()->overlapping($startsAt, $endsAt))
                ->whereDoesntHave('blocks', fn ($query) => $query->overlapping($startsAt, $endsAt));
        }
        if ($request->filled('guests')) $rooms->where('guests', '>=', (int) $request->guests);
        return view('rooms.index', [
            'rooms' => $rooms->orderBy('price_per_night')->get(),
            'filters' => $request->only('check_in', 'check_out', 'guests'),
        ]);
    }

    public function show(Room $room)
    {
        abort_unless($room->is_active, 404);
        $room->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews')->load(['approvedReviews' => fn ($query) => $query->latest()->take(8)]);
        return view('rooms.show', compact('room'));
    }
}
