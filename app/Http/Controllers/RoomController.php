<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Booking;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::where('is_active', true)
            ->withAvg('approvedReviews', 'rating')->withCount('approvedReviews');
        $checkIn = $request->date('check_in');
        $stay = $request->string('stay', 'day')->value();
        $checkInTime = preg_match('/^(0[6-9]|1[0-9]|2[0-3]):00$/', $request->string('check_in_time', '12:00')->value()) ? $request->string('check_in_time')->value() : '12:00';
        $hours = $stay === 'month' ? null : ($stay === 'day' ? 24 : (int) $stay);
        if ($checkIn && ($stay === 'month' || in_array($hours, [3, 6, 12, 24, 48, 72, 96, 120, 168], true))) {
            $startsAt = Carbon::parse($checkIn->toDateString() . ' ' . $checkInTime);
            $endsAt = $stay === 'month' ? $startsAt->copy()->addMonthNoOverflow() : $startsAt->copy()->addHours($hours);
            $rooms->whereDoesntHave('bookings', fn ($query) => $query->blocking()->overlapping($startsAt, $endsAt))
                ->whereDoesntHave('blocks', fn ($query) => $query->overlapping($startsAt, $endsAt));
        }
        if ($request->filled('guests')) $rooms->where('guests', '>=', (int) $request->guests);
        return view('rooms.index', [
            'rooms' => $rooms->orderBy('price_per_night')->get(),
            'filters' => $request->only('check_in', 'stay', 'check_in_time', 'guests'),
        ]);
    }

    public function show(Room $room)
    {
        abort_unless($room->is_active, 404);
        $room->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews')->load(['approvedReviews' => fn ($query) => $query->latest()->take(8)]);
        return view('rooms.show', compact('room'));
    }
}
