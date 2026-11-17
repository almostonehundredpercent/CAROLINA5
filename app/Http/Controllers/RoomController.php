<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Support\BookingSelection;
use App\Support\RoomAvailability;
use App\Support\StayPricing;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request, bool $bookingMode = false)
    {
        $rooms = Room::where('is_active', true)
            ->with(['promoCodes' => fn ($query) => $query->where('promo_codes.is_active', true)->where('promo_codes.review_status', 'approved')
                ->where(fn ($active) => $active->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->where(fn ($active) => $active->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit'))])
            ->withAvg('approvedReviews', 'rating')->withCount('approvedReviews');
        $filters = BookingSelection::query($request);
        if (isset($filters['check_in'])) {
            $filters += ['stay' => '3', 'check_in_time' => '12:00'];
        }
        if (isset($filters['guests'])) {
            $rooms->whereRaw('CASE WHEN guests < 2 THEN 2 ELSE guests END >= ?', [(int) $filters['guests']]);
        }
        $rooms->orderBy('id');

        [$startsAt, $endsAt] = RoomAvailability::window($filters);
        $roomListings = RoomAvailability::annotate($rooms->get(), $startsAt, $endsAt);
        $sort = $filters['sort'] ?? 'recommended';
        if (in_array($sort, ['price_asc', 'price_desc'], true)) {
            $direction = $sort === 'price_asc' ? 1 : -1;
            $roomListings = $roomListings->sort(function (Room $left, Room $right) use ($direction): int {
                $priceOrder = StayPricing::startingPrice($left) <=> StayPricing::startingPrice($right);

                return $priceOrder !== 0 ? $priceOrder * $direction : $left->id <=> $right->id;
            })->values();
        }

        return response()->view('rooms.index', [
            // Keep the numbered inventory in room-number order (the first six
            // are the original profiles and the following twelve are units).
            'rooms' => $roomListings,
            'filters' => $filters,
            'bookingMode' => $bookingMode,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function bookingStart(Request $request)
    {
        return $this->index($request, true);
    }

    public function show(Request $request, Room $room)
    {
        abort_unless($room->is_active, 404);
        $room->loadAvg('approvedReviews', 'rating')->loadCount('approvedReviews')->load([
            'approvedReviews' => fn ($query) => $query->latest()->take(8),
            'promoCodes' => fn ($query) => $query->where('promo_codes.is_active', true)->where('promo_codes.review_status', 'approved')
                ->where(fn ($active) => $active->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($active) => $active->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                ->where(fn ($active) => $active->whereNull('usage_limit')->orWhereColumn('times_used', '<', 'usage_limit')),
        ]);

        $bookingSearch = BookingSelection::query($request);
        if (isset($bookingSearch['check_in'])) {
            $bookingSearch += ['stay' => '3', 'check_in_time' => '12:00'];
        }
        [$startsAt, $endsAt] = RoomAvailability::window($bookingSearch);
        RoomAvailability::annotate(collect([$room]), $startsAt, $endsAt);

        $nextBookingMode = 'hourly';
        $stayHours = isset($bookingSearch['check_in'])
            ? BookingSelection::hours($bookingSearch, $room)
            : 3;
        if (! StayPricing::hasDuration($stayHours, $room)) {
            $stayHours = StayPricing::durations($room)[0] ?? 3;
        }
        $dateOnly = false;
        $nextAvailableAt = $room->availability_reason
            ? RoomAvailability::nextAvailableAt($room, $startsAt, $stayHours, $dateOnly)
            : null;
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
