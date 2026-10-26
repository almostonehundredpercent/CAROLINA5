<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RoomAvailability
{
    public static function window(array $selection): array
    {
        if (isset($selection['check_in'])) {
            $startsAt = Carbon::parse($selection['check_in'].' '.($selection['check_in_time'] ?? '12:00'));

            return [$startsAt, $startsAt->copy()->addHours(BookingSelection::hours($selection))];
        }

        $startsAt = now();

        return [$startsAt, $startsAt->copy()->addSecond()];
    }

    /** Mark rooms using the same overlap rules that protect reservations. */
    public static function annotate(Collection $rooms, Carbon $startsAt, Carbon $endsAt): Collection
    {
        $ids = $rooms->pluck('id');
        if ($ids->isEmpty()) {
            return $rooms;
        }

        $bookedIds = Booking::whereIn('room_id', $ids)->blocking()
            ->overlapping($startsAt, $endsAt)->pluck('room_id')->flip();
        $blocks = RoomBlock::whereIn('room_id', $ids)->overlapping($startsAt, $endsAt)
            ->orderBy('starts_at')->get(['room_id', 'status'])->keyBy('room_id');

        foreach ($rooms as $room) {
            $block = $blocks->get($room->id);
            $room->availability_reason = $block ? ucfirst($block->status)
                : ($bookedIds->has($room->id) ? 'Booked' : null);
            if (! $room->availability_reason && $room->operational_status !== 'available') {
                $room->availability_reason = ucfirst($room->operational_status);
            }
        }

        return $rooms;
    }
}
