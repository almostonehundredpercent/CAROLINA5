<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::where('is_active', true)->where('operational_status', 'available');
        $checkIn = $request->date('check_in');
        $checkOut = $request->date('check_out');
        if ($checkIn && $checkOut && $checkOut->gt($checkIn)) {
            $rooms->whereDoesntHave('bookings', fn ($query) => $query->whereIn('status', ['pending', 'confirmed'])->where('check_in', '<', $checkOut)->where('check_out', '>', $checkIn));
        }
        if ($request->filled('guests')) $rooms->where('guests', '>=', (int) $request->guests);
        return view('rooms.index', ['rooms' => $rooms->orderBy('price_per_night')->get(), 'filters' => $request->only('check_in', 'check_out', 'guests')]);
    }

    public function show(Room $room) { abort_unless($room->is_active, 404); return view('rooms.show', compact('room')); }
}
