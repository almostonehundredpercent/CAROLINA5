<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RoomBlock;
use Illuminate\Support\Collection;

class OperationalDecisionSupport
{
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
}
