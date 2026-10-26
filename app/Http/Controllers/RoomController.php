<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\BookingSelection;
use App\Support\RoomAvailability;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $rooms = Room::where('is_active', true)
            ->with(['promoCodes' => fn ($query) => $query->where('promo_codes.is_active', true)
                ->where(fn ($active) => $active->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->where(fn ($active) => $active->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit'))])
            ->withAvg('approvedReviews', 'rating')->withCount('approvedReviews');
        $filters = BookingSelection::query($request);
        if (isset($filters['check_in'])) {
            $filters += ['stay' => 'day', 'check_in_time' => '12:00'];
        }
        if (isset($filters['guests'])) {
            $rooms->where('guests', '>=', (int) $filters['guests']);
        }

        [$startsAt, $endsAt] = RoomAvailability::window($filters);

        return view('rooms.index', [
            // Keep the numbered inventory in room-number order (the first six
            // are the original profiles and the following twelve are units).
            'rooms' => RoomAvailability::annotate($rooms->orderBy('id')->get(), $startsAt, $endsAt),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, Room $room)
    {
        abort_unless($room->is_active, 404);
        $room->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews')->load([
            'approvedReviews' => fn ($query) => $query->latest()->take(8),
            'promoCodes' => fn ($query) => $query->where('promo_codes.is_active', true)
                ->where(fn ($active) => $active->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->where(fn ($active) => $active->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit')),
        ]);

        $bookingSearch = BookingSelection::query($request);
        if (isset($bookingSearch['check_in'])) {
            $bookingSearch += ['stay' => 'day', 'check_in_time' => '12:00'];
        }
        [$startsAt, $endsAt] = RoomAvailability::window($bookingSearch);
        RoomAvailability::annotate(collect([$room]), $startsAt, $endsAt);

        return view('rooms.show', compact('room', 'bookingSearch'));
    }
}
