<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DecisionSupportSeason;
use App\Models\DecisionSupportSetting;
use App\Models\PromoCode;
use App\Models\RoomBlock;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperationalDecisionSupport
{
    private const PROMO_WINDOW_DAYS = 90;

    private const PROMO_BUCKET_DAYS = 7;

    private const MIN_AVAILABLE_HOURS = 6;

    public function dashboardSignals(Collection $rooms, array $dismissedSignatures = []): Collection
    {
        $signals = collect();
        $windowStart = now()->subDays(90);
        $confirmed = Booking::where('status', 'confirmed')
            ->where('created_at', '>=', $windowStart)
            ->count();
        $cancelledQuery = Booking::where('status', 'cancelled')
            ->where('created_at', '>=', $windowStart);
        $cancelled = (clone $cancelledQuery)->count();
        $latestCancellationAt = (clone $cancelledQuery)->max('created_at');
        $expiredUnpaidHolds = (clone $cancelledQuery)
            ->where('cancellation_reason', 'Unpaid reservation hold expired.')
            ->count();
        $decided = $confirmed + $cancelled;
        $cancellationRate = $decided > 0 ? $cancelled / $decided * 100 : 0;
        $settings = DecisionSupportSetting::find(1);
        $latestExpiredHoldAt = (clone $cancelledQuery)
            ->where('cancellation_reason', 'Unpaid reservation hold expired.')
            ->max('cancelled_at');
        $latestExpiredHoldAt ??= (clone $cancelledQuery)
            ->where('cancellation_reason', 'Unpaid reservation hold expired.')
            ->max('created_at');
        $dismissedAfterLatestExpiry = $settings?->unpaid_hold_reminder_dismissed_at
            && $latestExpiredHoldAt
            && $settings->unpaid_hold_reminder_dismissed_at->greaterThanOrEqualTo(Carbon::parse($latestExpiredHoldAt));
        $elevated = $decided >= 5 && $cancellationRate >= 25;
        $holdExpiriesAreLeadingCause = $cancelled >= 3 && $expiredUnpaidHolds >= ceil($cancelled / 2);

        if ($elevated && $holdExpiriesAreLeadingCause
            && ! $settings?->unpaid_hold_reminders_enabled
            && ! $dismissedAfterLatestExpiry) {
            $signals->push([
                'kind' => 'unpaid_hold_payment_reminder',
                'title' => 'Unpaid holds are expiring before payment',
                'evidence' => "{$expiredUnpaidHolds} of {$cancelled} cancellations among bookings created in the last 90 days were unpaid holds that expired.",
                'recommendation' => 'Send one payment reminder five minutes before an unpaid hold expires.',
                'rule' => 'At least 5 bookings created in the last 90 days, a cancellation rate of 25% or higher, and 3 or more expired holds making up at least half of cancellations.',
            ]);
        } elseif ($elevated && ! $settings?->unpaid_hold_reminders_enabled && ! $dismissedAfterLatestExpiry) {
            $displayRate = (int) round($cancellationRate);
            $signals->push([
                'kind' => 'elevated_cancellations',
                'signature' => hash('sha256', "cancellations:{$cancelled}:{$decided}:{$latestCancellationAt}"),
                'title' => 'Cancellation activity is elevated',
                'evidence' => "{$cancelled} of {$decided} bookings created in the last 90 days were cancelled ({$displayRate}%).",
                'rule' => 'At least 5 bookings created in the last 90 days and a cancellation rate of 25% or higher.',
                'recommendation' => 'Confirm to review recent cancelled bookings here.',
            ]);
        }

        if ($rooms->isNotEmpty() && $rooms->where('display_status', 'available')->isEmpty()) {
            $availabilityState = $rooms->sortBy('id')->map(fn ($room) => $room->id.':'.$room->display_status)->implode('|');
            $signals->push([
                'kind' => 'no_rooms_available',
                'signature' => hash('sha256', 'availability:'.$availabilityState),
                'title' => 'No rooms are available right now',
                'evidence' => "All {$rooms->count()} active rooms are currently reserved or unavailable.",
                'rule' => 'Triggered when no active room is marked available at this moment.',
                'recommendation' => 'Confirm to review today’s arrivals here.',
            ]);
        }

        $maintenance = RoomBlock::where('status', 'maintenance')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('room_id, count(*) as total, max(created_at) as latest_at')
            ->groupBy('room_id')
            ->havingRaw('COUNT(*) >= ?', [3])
            ->with('room')
            ->get();

        foreach ($maintenance as $block) {
            $roomName = $block->room?->name ?? 'A room';
            $signals->push([
                'kind' => 'repeated_maintenance',
                'room_id' => (int) $block->room_id,
                'signature' => hash('sha256', "maintenance:{$block->room_id}:{$block->total}:{$block->latest_at}"),
                'title' => "Review maintenance for {$roomName}",
                'evidence' => "{$block->total} maintenance blocks were recorded for this room in the last 30 days.",
                'rule' => 'Triggered at 3 or more maintenance blocks for one room within 30 days.',
                'recommendation' => 'Confirm to review the room’s recent maintenance entries here.',
            ]);
        }

        $topRoom = Booking::where('status', 'confirmed')
            ->selectRaw('room_id, count(*) as total, max(created_at) as latest_at')
            ->groupBy('room_id')
            ->orderByDesc('total')
            ->with('room')
            ->first();

        if ($topRoom && $topRoom->total >= 5) {
            $roomName = $topRoom->room?->name ?? 'One room';
            $signals->push([
                'kind' => 'high_booking_demand',
                'room_id' => (int) $topRoom->room_id,
                'signature' => hash('sha256', "demand:{$topRoom->room_id}:{$topRoom->total}:{$topRoom->latest_at}"),
                'title' => "High booking demand for {$roomName}",
                'evidence' => "{$topRoom->total} confirmed stays are recorded for this room.",
                'rule' => 'Triggered at 5 or more confirmed stays across recorded history.',
                'recommendation' => 'Confirm to review the room’s upcoming bookings here.',
            ]);
        }

        $dismissedSignatures = array_flip($dismissedSignatures);
        $zeroGapTurnovers = $this->upcomingTurnovers()
            ->filter(fn (array $turnover) => $turnover['gap_minutes'] === 0)
            ->reverse();
        foreach ($zeroGapTurnovers as $zeroGapTurnover) {
            if (isset($dismissedSignatures[$zeroGapTurnover['signature']])) {
                continue;
            }

            $roomName = $zeroGapTurnover['room']?->name ?? 'A room';
            $signature = $zeroGapTurnover['signature'];
            $signals->prepend([
                'kind' => 'zero_turnover_gap',
                'room_id' => (int) $zeroGapTurnover['room']?->id,
                'signature' => $signature,
                'departure_booking_id' => $zeroGapTurnover['departure_booking_id'],
                'next_arrival_booking_id' => $zeroGapTurnover['next_arrival_booking_id'],
                'title' => "No room-prep time for {$roomName}",
                'evidence' => 'Checkout and the next check-in are scheduled for the same time on '.$zeroGapTurnover['check_out_at']->format('D, M j · g:i A').'.',
                'rule' => 'Triggered when confirmed stays in one room have identical checkout and next check-in times within the next 7 days.',
                'recommendation' => 'Confirm to review both bookings here.',
            ]);
        }

        return $signals->reject(fn (array $signal) => isset($dismissedSignatures[$signal['signature'] ?? '']))->values();
    }

    /** Summarize only the selected report's recorded data and show its rule. */
    public function reportInsight(string $metric, array $financialMetrics, ?array $bookingMetrics, ?array $guestMetrics): array
    {
        if ($metric === 'payments') {
            $ledger = $financialMetrics['period_ledger'] ?? ['paid' => 0, 'refunds' => 0, 'net_collected' => 0];

            return [
                'title' => 'Recorded payment summary',
                'evidence' => 'This period shows ₱'.number_format((float) $ledger['paid'], 2).' paid, ₱'.number_format((float) $ledger['refunds'], 2).' refunded, and ₱'.number_format((float) $ledger['net_collected'], 2).' net. Outstanding balances total ₱'.number_format((float) ($financialMetrics['outstanding_balance'] ?? 0), 2).'.',
                'rule' => 'Paid and refunded totals come from payment entries dated in the selected period; outstanding balance is for bookings created in the period.',
                'action_label' => 'Open payment report',
                'action_url' => route('admin.reports', ['metric' => 'payments']),
            ];
        }

        if ($metric === 'bookings') {
            $total = (int) ($bookingMetrics['total'] ?? 0);
            $cancelled = (int) ($bookingMetrics['cancelled'] ?? 0);
            $cancellationRate = $total > 0 ? $cancelled / $total * 100 : 0;
            $rate = (int) round($cancellationRate);
            $elevated = $total >= 5 && $cancellationRate >= 25;

            return [
                'title' => $elevated ? 'Cancellation activity is elevated' : 'Booking activity in this period',
                'evidence' => $elevated
                    ? "{$cancelled} of {$total} bookings created in this period were cancelled ({$rate}%)."
                    : "{$total} bookings were created and {$cancelled} were cancelled in this period.",
                'rule' => $elevated
                    ? 'Flagged with at least 5 bookings and a cancellation rate of 25% or higher.'
                    : 'Uses booking creation dates and recorded booking status; no forecast is applied.',
                'action_label' => 'Review bookings',
                'action_url' => route('admin.bookings'),
            ];
        }

        $guestMetrics ??= ['total' => 0, 'new' => 0, 'returning' => 0];

        return [
            'title' => 'Guest return activity',
            'evidence' => ($guestMetrics['new'] ?? 0).' first-time and '.($guestMetrics['returning'] ?? 0).' returning guests appear among '.($guestMetrics['total'] ?? 0).' unique guests in this period.',
            'rule' => 'First-time and returning status uses earlier booking records on file; it does not infer guest intent.',
            'action_label' => 'Review guest report',
            'action_url' => route('admin.reports', ['metric' => 'guests']),
        ];
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
                'departure_booking_id' => $departure->id,
                'next_arrival_booking_id' => $nextArrival->id,
                'check_out_at' => $departure->check_out_at,
                'next_check_in_at' => $nextArrival->check_in_at,
                'gap_minutes' => $gapMinutes,
                'gap_label' => $gapLabel,
                'signature' => hash('sha256', 'zero_turnover_gap:'.$departure->room_id.':'.$departure->id.':'.$nextArrival->id.':'.$departure->check_out_at->toIso8601String().':'.$nextArrival->check_in_at->toIso8601String()),
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

    /** Create inactive, idempotent promo drafts from CAROL's low-occupancy windows. */
    public function generatePromoDrafts(Collection $opportunities, Collection $seasons, int $threshold): int
    {
        PromoCode::where('review_status', 'pending')->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->update(['review_status' => 'expired', 'is_active' => false]);

        $created = 0;

        foreach ($opportunities as $opportunity) {
            $windowStart = $opportunity['starts_at']->copy()->startOfDay();
            $windowEnd = $opportunity['ends_at']->copy()->startOfDay();
            $segments = [];
            $date = $windowStart->copy();

            while ($date->lte($windowEnd)) {
                $dateString = $date->toDateString();
                $event = $seasons->filter(fn (DecisionSupportSeason $season) =>
                    $season->starts_on->toDateString() <= $dateString
                    && $season->ends_on->toDateString() >= $dateString
                )->sortBy(fn (DecisionSupportSeason $season) => $season->kind === 'holiday' ? 0 : 1)->first();
                $category = $event
                    ? ($event->kind === 'holiday' ? 'holiday' : 'seasonal')
                    : ($date->isWeekend() ? 'weekend' : 'weekday');
                $eventName = $event?->name;
                $segmentKey = $category.':'.($eventName ?? '');
                $lastIndex = array_key_last($segments);

                if ($lastIndex !== null && $segments[$lastIndex]['key'] === $segmentKey) {
                    $segments[$lastIndex]['ends_on'] = $date->copy();
                } else {
                    $segments[] = [
                        'key' => $segmentKey,
                        'category' => $category,
                        'event_name' => $eventName,
                        'starts_on' => $date->copy(),
                        'ends_on' => $date->copy(),
                    ];
                }

                $date->addDay();
            }

            foreach ($segments as $segment) {
                $created += $this->createPromoDraft(
                    $opportunity,
                    $segment['category'],
                    $segment['starts_on'],
                    $segment['ends_on'],
                    $threshold,
                    $segment['event_name'],
                ) ? 1 : 0;
            }
        }

        return $created;
    }

    private function createPromoDraft(array $opportunity, string $category, $startsOn, $endsOn, int $threshold, ?string $eventName = null): bool
    {
        $room = $opportunity['room'];
        $startDate = $startsOn->toDateString();
        $endDate = $endsOn->toDateString();
        $categoryCode = match ($category) {
            'weekday' => 'WD',
            'weekend' => 'WE',
            'holiday' => 'HO',
            default => 'SE',
        };
        $generationKey = implode(':', ['room', $room->id, $category, $startDate, $endDate]);
        $code = 'CAROL-'.$categoryCode.'-R'.$room->id.'-'.$startsOn->format('ymd').'-'.$endsOn->format('ymd');
        $categoryLabel = match ($category) {
            'weekday' => 'weekday',
            'weekend' => 'weekend',
            'holiday' => 'holiday',
            default => 'seasonal',
        };
        $dateLabel = $startsOn->format('M j').'–'.$endsOn->format('M j, Y');
        $stayDayType = in_array($category, ['weekday', 'weekend'], true) ? $category : null;
        $name = 'CAROL '.ucfirst($categoryLabel).' offer — '.$room->name;
        $description = match ($category) {
            'weekday' => '10% off stays checking in Monday through Friday, '.$dateLabel.'.',
            'weekend' => '10% off stays checking in Saturday or Sunday, '.$dateLabel.'.',
            default => '10% off stays during '.($eventName ?: $categoryLabel).' dates, '.$dateLabel.'.',
        };
        $notes = 'CAROL found '.$opportunity['utilization'].'% weekly confirmed occupancy, below the '.$threshold.'% rule, with '.number_format((float) $opportunity['available_hours'], 1).' hours still open. Suggested discount: 10%. Review this inactive draft before activation.';

        $promo = PromoCode::firstOrCreate(
            ['carol_generation_key' => $generationKey],
            [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'starts_at' => now(),
                'ends_at' => $endsOn->copy()->endOfDay(),
                'is_active' => false,
                'review_status' => 'pending',
                'carol_category' => $category,
                'carol_notes' => $notes,
                'stay_start_date' => $startDate,
                'stay_end_date' => $endDate,
                'stay_day_type' => $stayDayType,
            ]
        );

        if ($promo->wasRecentlyCreated) {
            $promo->rooms()->sync([$room->id]);
        }

        return $promo->wasRecentlyCreated;
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
