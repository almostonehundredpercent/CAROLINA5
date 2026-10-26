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

    /** Find the next bookable start, including later bookings and room operations. */
    public static function nextAvailableAt(Room $room, Carbon $from, int $hours, bool $dateOnly = false): ?Carbon
    {
        if ($room->operational_status !== 'available' && (! $room->operational_until || $room->operational_until->lte(now()))) {
            return null; // A manual closure without a future end cannot be predicted.
        }

        $intervals = Booking::where('room_id', $room->id)->blocking()
            ->where(fn ($query) => $query->where('check_out_at', '>', $from)
                ->orWhere(fn ($legacy) => $legacy->whereNull('check_in_at')->whereDate('check_out', '>=', $from->toDateString())))
            ->get(['check_in', 'check_out', 'check_in_at', 'check_out_at'])
            ->map(fn (Booking $booking) => [
                $booking->check_in_at ?? $booking->check_in->copy()->startOfDay(),
                $booking->check_out_at ?? $booking->check_out->copy()->startOfDay(),
            ])->all();

        $blocks = RoomBlock::where('room_id', $room->id)->whereNull('completed_at')
            ->where(fn ($query) => $query->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->where('ends_at', '>', $from)->get(['starts_at', 'ends_at']);
        foreach ($blocks as $block) {
            $intervals[] = [$block->starts_at, $block->ends_at];
        }
        if ($room->operational_status !== 'available') {
            $intervals[] = [now(), $room->operational_until];
        }

        usort($intervals, fn ($left, $right) => $left[0]->getTimestamp() <=> $right[0]->getTimestamp());
        $candidate = $from->greaterThan(now()) ? $from->copy() : now();

        // Each collision moves past at least one interval; one final pass finds a gap.
        for ($attempt = 0; $attempt <= count($intervals); $attempt++) {
            $candidate = $dateOnly ? $candidate->copy()->ceilDay() : self::nextCheckInHour($candidate);
            $end = $candidate->copy()->addHours($hours);
            $collision = null;
            foreach ($intervals as [$startsAt, $endsAt]) {
                if ($startsAt->lt($end) && $endsAt->gt($candidate)
                    && (! $collision || $endsAt->gt($collision))) {
                    $collision = $endsAt;
                }
            }
            if (! $collision) {
                return $candidate;
            }
            $candidate = $collision->copy();
        }

        return null;
    }

    private static function nextCheckInHour(Carbon $time): Carbon
    {
        $hour = $time->copy()->startOfHour();
        if ($hour->lt($time)) {
            $hour->addHour();
        }
        if ($hour->hour < 6) {
            return $hour->setTime(6, 0);
        }
        if ($hour->hour > 23) {
            return $hour->addDay()->setTime(6, 0);
        }

        return $hour;
    }
}
