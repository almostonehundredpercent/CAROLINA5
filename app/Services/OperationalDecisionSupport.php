<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DecisionSupportSeason;
use App\Models\RoomBlock;
use Illuminate\Support\Collection;

class OperationalDecisionSupport
{
    private const PROMO_WINDOW_DAYS = 90;

    private const PROMO_BUCKET_DAYS = 7;

    private const MIN_AVAILABLE_HOURS = 6;

    public function dashboardSignals(Collection $rooms): Collection
    {
        $signals = collect();
        $confirmed = Booking::where('status', 'confirmed')->count();
        $cancelled = Booking::where('status', 'cancelled')->count();
        $decided = $confirmed + $cancelled;

        if ($decided >= 5) {
            $cancellationRate = $cancelled / $decided * 100;
            if ($cancellationRate >= 25) {
                $displayRate = (int) round($cancellationRate);
                $signals->push([
                    'title' => 'Cancellation activity is elevated',
                    'evidence' => "{$cancelled} of {$decided} decided bookings were cancelled ({$displayRate}%).",
                    'rule' => 'At least 5 decided bookings and a cancellation rate of 25% or higher.',
                    'action_label' => 'Review booking requests',
                    'action_url' => route('admin.bookings'),
                ]);
            }
        }

        if ($rooms->isNotEmpty() && $rooms->where('display_status', 'available')->isEmpty()) {
            $signals->push([
                'title' => 'No rooms are available right now',
                'evidence' => "All {$rooms->count()} active rooms are currently reserved or unavailable.",
                'rule' => 'Triggered when no active room is marked available at this moment.',
                'action_label' => 'Check today’s arrivals',
                'action_url' => route('admin.arrivals'),
            ]);
        }

        $maintenance = RoomBlock::where('status', 'maintenance')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('room_id, count(*) as total')
            ->groupBy('room_id')
            ->having('total', '>=', 3)
            ->with('room')
            ->get();

        foreach ($maintenance as $block) {
            $roomName = $block->room?->name ?? 'A room';
            $signals->push([
                'title' => "Review maintenance for {$roomName}",
                'evidence' => "{$block->total} maintenance blocks were recorded for this room in the last 30 days.",
                'rule' => 'Triggered at 3 or more maintenance blocks for one room within 30 days.',
                'action_label' => 'Review room operations',
                'action_url' => route('admin.rooms'),
            ]);
        }

        $topRoom = Booking::where('status', 'confirmed')
            ->selectRaw('room_id, count(*) as total')
            ->groupBy('room_id')
            ->orderByDesc('total')
            ->with('room')
            ->first();

        if ($topRoom && $topRoom->total >= 5) {
            $roomName = $topRoom->room?->name ?? 'One room';
            $signals->push([
                'title' => "High booking demand for {$roomName}",
                'evidence' => "{$topRoom->total} confirmed stays are recorded for this room.",
                'rule' => 'Triggered at 5 or more confirmed stays across recorded history.',
                'action_label' => 'Review room schedule',
                'action_url' => route('admin.rooms'),
            ]);
        }

        return $signals;
    }

    public function upcomingTurnovers(): Collection
    {
        $now = now();
        $windowEnd = $now->copy()->addDays(7)->endOfDay();
        $stays = Booking::with('room')
            ->where('status', 'confirmed')
            ->whereNull('checked_out_at')
            ->where(fn ($query) => $query
                ->whereBetween('check_in_at', [$now, $windowEnd])
                ->orWhereBetween('check_out_at', [$now, $windowEnd]))
            ->orderBy('check_in_at')
            ->get();

        $departures = $stays->filter(fn (Booking $booking) => $booking->check_out_at
            && $booking->check_out_at->betweenIncluded($now, $windowEnd));

        return $departures->map(function (Booking $departure) use ($stays) {
            $nextArrival = $stays->first(fn (Booking $candidate) => $candidate->id !== $departure->id
                && $candidate->room_id === $departure->room_id
                && $candidate->check_in_at
                && $candidate->check_in_at->greaterThanOrEqualTo($departure->check_out_at)
                && $candidate->check_in_at->toDateString() === $departure->check_out_at->toDateString());

            if (! $nextArrival) {
                return null;
            }

            $gapMinutes = (int) floor(($nextArrival->check_in_at->getTimestamp() - $departure->check_out_at->getTimestamp()) / 60);
            $gapMinutes = max(0, $gapMinutes);
            $gapHours = intdiv($gapMinutes, 60);
            $remainingMinutes = $gapMinutes % 60;
            $gapLabel = $gapHours > 0
                ? $gapHours.' hour'.($gapHours === 1 ? '' : 's').($remainingMinutes > 0 ? ', '.$remainingMinutes.' min' : '')
                : $gapMinutes.' minutes';

            return [
                'room' => $departure->room,
                'check_out_at' => $departure->check_out_at,
                'next_check_in_at' => $nextArrival->check_in_at,
                'gap_minutes' => $gapMinutes,
                'gap_label' => $gapLabel,
            ];
        })->filter()->sortBy('gap_minutes')->take(8)->values();
    }

    /**
     * Find date windows where confirmed room-time utilization is low and the
     * room still has usable inventory. This is a schedule-based rule, not a
     * demand forecast or a claim that a promotion will cause more bookings.
     */
    public function promoOpportunities(Collection $rooms, int $threshold, Collection $seasons): Collection
    {
        if ($rooms->isEmpty()) {
            return collect();
        }

        $horizonStart = now();
        $horizonEnd = today()->addDays(self::PROMO_WINDOW_DAYS - 1)->endOfDay();
        $roomIds = $rooms->pluck('id');
        $inventoryBookings = Booking::query()->blocking()->whereIn('room_id', $roomIds)
            ->whereNull('checked_out_at')->get();
        $operationalBlocks = RoomBlock::query()->overlapping($horizonStart, $horizonEnd)
            ->whereIn('status', ['maintenance', 'cleaning'])->whereIn('room_id', $roomIds)->get();

        $opportunities = collect();
        $windowStart = $horizonStart->copy();

        while ($windowStart->lt($horizonEnd)) {
            $windowEnd = $windowStart->copy()->startOfDay()->addDays(self::PROMO_BUCKET_DAYS);
            if ($windowEnd->gt($horizonEnd)) {
                $windowEnd = $horizonEnd->copy();
            }

            $endLabel = $windowEnd->copy()->subSecond();
            $seasonNames = $seasons->filter(fn (DecisionSupportSeason $season) =>
                $season->starts_on->startOfDay()->lt($windowEnd)
                && $season->ends_on->endOfDay()->gte($windowStart)
            )->pluck('name')->values();

            foreach ($rooms as $room) {
                $roomBookings = $inventoryBookings->where('room_id', $room->id);
                $roomBlocks = $operationalBlocks->where('room_id', $room->id);
                $allBookingIntervals = $roomBookings->map(fn (Booking $booking) => $this->bookingInterval($booking))
                    ->filter()->values()->all();
                $confirmedIntervals = $roomBookings->where('status', 'confirmed')
                    ->map(fn (Booking $booking) => $this->bookingInterval($booking))->filter()->values()->all();
                $operationIntervals = $roomBlocks->map(fn (RoomBlock $block) => [
                    $block->starts_at->getTimestamp(),
                    $block->ends_at->getTimestamp(),
                ])->all();

                $window = [$windowStart->getTimestamp(), $windowEnd->getTimestamp()];
                $operationSeconds = $this->durationWithin($operationIntervals, $window);
                $sellableSeconds = max(0, ($window[1] - $window[0]) - $operationSeconds);
                if ($sellableSeconds <= 0) {
                    continue;
                }

                $confirmedSeconds = $this->durationWithin($confirmedIntervals, $window, $operationIntervals);
                $reservedSeconds = $this->durationWithin(array_merge($allBookingIntervals, $operationIntervals), $window);
                $availableSeconds = max(0, ($window[1] - $window[0]) - $reservedSeconds);
                $utilization = $confirmedSeconds / $sellableSeconds * 100;
                $availableHours = $availableSeconds / 3600;

                if ($utilization >= $threshold || $availableHours < self::MIN_AVAILABLE_HOURS) {
                    continue;
                }

                $windowBookings = $roomBookings->filter(function (Booking $booking) use ($window) {
                    $interval = $this->bookingInterval($booking);

                    return $interval && $interval[0] < $window[1] && $interval[1] > $window[0];
                });

                $opportunities->push([
                    'room' => $room,
                    'starts_at' => $windowStart->copy(),
                    'ends_at' => $endLabel,
                    'utilization' => (int) round($utilization),
                    'confirmed_hours' => round($confirmedSeconds / 3600, 1),
                    'sellable_hours' => round($sellableSeconds / 3600, 1),
                    'available_hours' => round($availableHours, 1),
                    'hourly_stays' => $windowBookings->filter(fn (Booking $booking) => $booking->booking_type === 'hourly')->count(),
                    'date_stays' => $windowBookings->filter(fn (Booking $booking) => $booking->booking_type !== 'hourly')->count(),
                    'season_names' => $seasonNames,
                    'threshold' => $threshold,
                    'minimum_available_hours' => self::MIN_AVAILABLE_HOURS,
                ]);
            }

            $windowStart = $windowEnd->copy();
        }

        return $opportunities->sort(function (array $left, array $right) {
            $leftSeasonal = $left['season_names']->isNotEmpty();
            $rightSeasonal = $right['season_names']->isNotEmpty();
            if ($leftSeasonal !== $rightSeasonal) {
                return $leftSeasonal ? -1 : 1;
            }
            if ($left['utilization'] !== $right['utilization']) {
                return $left['utilization'] <=> $right['utilization'];
            }

            return $right['available_hours'] <=> $left['available_hours'];
        })->unique(fn (array $opportunity) => $opportunity['room']->id)->take(12)->values();
    }

    /**
     * Summarize recorded promo-code bookings without attributing causation to
     * the promotion. These are redemption and booking outcomes only.
     */
    public function promoOutcomes(): Collection
    {
        return Booking::query()->whereNotNull('promo_code')->where('created_at', '>=', now()->subDays(90))
            ->get(['promo_code', 'status', 'payment_status', 'checked_out_at'])
            ->filter(fn (Booking $booking) => trim((string) $booking->promo_code) !== '')
            ->groupBy(fn (Booking $booking) => strtoupper(trim($booking->promo_code)))
            ->map(fn (Collection $bookings, string $code) => [
                'code' => $code,
                'applied' => $bookings->count(),
                'confirmed' => $bookings->where('status', 'confirmed')->count(),
                'paid' => $bookings->where('payment_status', 'paid')->count(),
                'completed' => $bookings->whereNotNull('checked_out_at')->count(),
                'cancelled' => $bookings->where('status', 'cancelled')->count(),
                'refunded' => $bookings->where('payment_status', 'refunded')->count(),
            ])->sortByDesc('applied')->values();
    }

    private function bookingInterval(Booking $booking): ?array
    {
        if (! $booking->check_in || ! $booking->check_out) {
            return null;
        }

        $start = ($booking->check_in_at ?? $booking->check_in->copy()->startOfDay())->getTimestamp();
        $end = ($booking->check_out_at ?? $booking->check_out->copy()->startOfDay())->getTimestamp();

        return $end > $start ? [$start, $end] : null;
    }

    /** Measure the union of intervals clipped to a window, optionally excluding other intervals. */
    private function durationWithin(array $intervals, array $window, array $excluded = []): int
    {
        $clipped = collect($intervals)->map(function (array $interval) use ($window) {
            $start = max($window[0], $interval[0]);
            $end = min($window[1], $interval[1]);

            return $end > $start ? [$start, $end] : null;
        })->filter()->all();
        $merged = $this->mergeIntervals($clipped);

        if ($excluded === []) {
            return array_sum(array_map(fn (array $interval) => $interval[1] - $interval[0], $merged));
        }

        $exclusions = $this->mergeIntervals(collect($excluded)->map(function (array $interval) use ($window) {
            $start = max($window[0], $interval[0]);
            $end = min($window[1], $interval[1]);

            return $end > $start ? [$start, $end] : null;
        })->filter()->all());

        $remaining = 0;
        foreach ($merged as [$start, $end]) {
            $cursor = $start;
            foreach ($exclusions as [$excludedStart, $excludedEnd]) {
                if ($excludedEnd <= $cursor) {
                    continue;
                }
                if ($excludedStart >= $end) {
                    break;
                }
                $remaining += max(0, min($end, $excludedStart) - $cursor);
                $cursor = max($cursor, $excludedEnd);
                if ($cursor >= $end) {
                    break;
                }
            }
            $remaining += max(0, $end - $cursor);
        }

        return $remaining;
    }

    private function mergeIntervals(array $intervals): array
    {
        usort($intervals, fn (array $left, array $right) => $left[0] <=> $right[0]);
        $merged = [];

        foreach ($intervals as [$start, $end]) {
            if ($merged === [] || $start > $merged[array_key_last($merged)][1]) {
                $merged[] = [$start, $end];
                continue;
            }
            $last = array_key_last($merged);
            $merged[$last][1] = max($merged[$last][1], $end);
        }

        return $merged;
    }
}
