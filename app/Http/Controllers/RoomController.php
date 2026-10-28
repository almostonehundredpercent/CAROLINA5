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
        if (($filters['sort'] ?? 'recommended') === 'price_asc') {
            $rooms->orderBy('price_per_night')->orderBy('id');
        } elseif (($filters['sort'] ?? 'recommended') === 'price_desc') {
            $rooms->orderByDesc('price_per_night')->orderBy('id');
        } else {
            $rooms->orderBy('id');
        }

        [$startsAt, $endsAt] = RoomAvailability::window($filters);

        return response()->view('rooms.index', [
            // Keep the numbered inventory in room-number order (the first six
            // are the original profiles and the following twelve are units).
            'rooms' => RoomAvailability::annotate($rooms->get(), $startsAt, $endsAt),
            'filters' => $filters,
        ])->header('Cache-Control', 'private, no-store');
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

        $stayHours = isset($bookingSearch['check_in'])
            ? BookingSelection::hours($bookingSearch, $room)
            : ($room->rental_hours ?: 24);
        $dateOnly = ! $room->rental_hours && ! isset($bookingSearch['stay'])
            && ($bookingSearch['mode'] ?? 'dates') === 'dates';
        $nextAvailableAt = $room->availability_reason
            ? RoomAvailability::nextAvailableAt($room, $startsAt, $stayHours, $dateOnly)
            : null;
        $nextBookingMode = $room->rental_hours
            ? 'hourly'
            : ($bookingSearch['mode'] ?? (isset($bookingSearch['stay']) ? 'hourly' : 'dates'));
        $nextBookingUrl = null;
        if ($nextAvailableAt) {
            $nextBookingParams = [
                'room' => $room,
                'mode' => $nextBookingMode,
                'check_in' => $nextAvailableAt->toDateString(),
            ];
            if ($nextBookingMode === 'hourly') {
                $nextBookingParams['check_in_time'] = $nextAvailableAt->format('H:i');
                $nextBookingParams['stay'] = $stayHours;
            }
            if (! auth()->check()) {
                $nextBookingParams['guest'] = 1;
            }
            $nextBookingUrl = route('bookings.create', $nextBookingParams);
        }

        return response()->view('rooms.show', compact('room', 'bookingSearch', 'stayHours', 'nextAvailableAt', 'nextBookingUrl', 'nextBookingMode'))
            ->header('Cache-Control', 'private, no-store');
    }
}
