<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use App\Support\PropertyTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestBookingRescheduleService
{
    public function reschedule(Booking $booking, Carbon $newStart, ?int $actorId = null): Booking
    {
        return DB::transaction(function () use ($booking, $newStart, $actorId) {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $locked->canRescheduleOnline()) {
                throw ValidationException::withMessages(['reschedule' => $locked->reschedulesRemaining() < 1
                    ? 'This booking has used its 2 online reschedules.'
                    : 'Online rescheduling is available only for active stays at least 3 days before check-in.']);
            }
            if (! $room->is_active) {
                throw ValidationException::withMessages(['reschedule' => 'This room is no longer available for online rescheduling. Please contact Carolina.']);
            }
            if (PropertyTime::now()->gt($newStart->copy()->subDays(3))) {
                throw ValidationException::withMessages(['reschedule' => 'Choose a new check-in at least 3 days from now.']);
            }
            if ($locked->timeAdjustments()->whereIn('status', ['pending', 'needs_review'])->exists()) {
                throw ValidationException::withMessages(['reschedule' => 'A room-time change is awaiting payment or staff review. Please contact Carolina before rescheduling.']);
            }

            $oldStart = $locked->check_in_at ?? $locked->check_in->copy()->startOfDay();
            $oldEnd = $locked->check_out_at ?? $locked->check_out->copy()->startOfDay();
            $durationSeconds = $oldStart->diffInSeconds($oldEnd, false);
            if ($durationSeconds <= 0 || $newStart->equalTo($oldStart)) {
                throw ValidationException::withMessages(['reschedule' => 'Choose a different valid check-in time.']);
            }
            $newEnd = $newStart->copy()->addSeconds($durationSeconds);
            $bookingConflict = $room->bookings()->blocking()->overlapping($newStart, $newEnd)->whereKeyNot($locked->id)->exists();
            $blockConflict = $room->blocks()->overlapping($newStart, $newEnd)->exists();
            if ($bookingConflict || $blockConflict) {
                throw ValidationException::withMessages(['reschedule' => 'The room is reserved or unavailable for those dates. Choose another check-in time.']);
            }

            $before = [
                'check_in_at' => $oldStart->toIso8601String(),
                'check_out_at' => $oldEnd->toIso8601String(),
                'reschedule_count' => (int) $locked->reschedule_count,
            ];
            $locked->check_in_at = $newStart;
            $locked->check_out_at = $newEnd;
            $locked->check_in = $newStart->toDateString();
            $locked->check_out = $newEnd->toDateString();
            $locked->nights = max(1, (int) ceil($durationSeconds / 86400));
            $locked->reschedule_count = (int) $locked->reschedule_count + 1;
            $locked->save();

            ActivityLog::create([
                'booking_id' => $locked->id,
                'user_id' => $actorId,
                'event' => 'booking_rescheduled',
                'description' => 'Guest rescheduled their booking online.',
                'before_values' => $before,
                'after_values' => [
                    'check_in_at' => $newStart->toIso8601String(),
                    'check_out_at' => $newEnd->toIso8601String(),
                    'reschedule_count' => (int) $locked->reschedule_count,
                ],
            ]);

            return $locked->fresh(['room', 'user', 'payments']);
        });
    }
}
