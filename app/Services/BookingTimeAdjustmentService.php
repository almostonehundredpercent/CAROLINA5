<?php

namespace App\Services;

use App\Mail\BookingUpdate;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingTimeAdjustmentService
{
    public function prepare(Booking $booking, string $type, int $hours, ?int $actorId = null): BookingTimeAdjustment
    {
        if (! in_array($type, ['early_checkin', 'late_checkout'], true) || $hours < 1
            || ($type === 'early_checkin' && $hours > 2) || ($type === 'late_checkout' && $hours > 168)) {
            throw ValidationException::withMessages(['hours' => 'Choose a valid amount of additional stay time.']);
        }

        return DB::transaction(function () use ($booking, $type, $hours, $actorId) {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $locked = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'confirmed' || $locked->checked_out_at, 422, 'Only active confirmed bookings can change stay times.');
            abort_if($locked->payment_status !== 'paid' && ! $locked->paid_at, 422, 'The original booking must be fully paid before changing stay times.');
            $expiredAdjustments = $locked->timeAdjustments()->where('status', 'pending')
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '<=', now()))
                ->lockForUpdate()->get();
            foreach ($expiredAdjustments as $expiredAdjustment) {
                $expiredAdjustment->update(['status' => 'expired', 'resolution_note' => 'The temporary room-time hold expired before payment completed.']);
                if ($expiredAdjustment->room_block_id) {
                    RoomBlock::whereKey($expiredAdjustment->room_block_id)->update(['completed_at' => now()]);
                }
            }
            abort_if($locked->timeAdjustments()->where('status', 'pending')->where('expires_at', '>', now())->exists(), 422, 'Finish or cancel the existing time change before starting another.');

            $currentStart = $locked->check_in_at ?? $locked->check_in->copy()->startOfDay();
            $currentEnd = $locked->check_out_at ?? $locked->check_out->copy()->startOfDay();
            if ($type === 'early_checkin') {
                abort_if($locked->checked_in_at, 422, 'This guest has already checked in.');
                $hoursUntilArrival = (int) ceil(max(0, $currentStart->getTimestamp() - now()->getTimestamp()) / 3600);
                abort_if(! $locked->check_in->isToday() || $currentStart->lte(now()) || $hoursUntilArrival < 1 || $hoursUntilArrival > 2 || $hours !== $hoursUntilArrival, 422, 'Early check-in is available within the next two hours. The fee is rounded to the next hour.');
                $proposedStart = $currentStart->copy()->subHours($hours);
                $proposedEnd = $currentEnd->copy();
                $holdStart = $proposedStart;
                $holdEnd = $currentStart;
            } else {
                abort_if($currentEnd->lte(now()), 422, 'The scheduled checkout time has passed. Contact a manager to change this stay.');
                $proposedStart = $currentStart->copy();
                $proposedEnd = $currentEnd->copy()->addHours($hours);
                $holdStart = $currentEnd;
                $holdEnd = $proposedEnd;
            }

            $conflict = $room->bookings()->whereKeyNot($locked->id)->blocking()->overlapping($holdStart, $holdEnd)->exists()
                || $room->blocks()->overlapping($holdStart, $holdEnd)->exists();
            if ($conflict) {
                throw ValidationException::withMessages(['hours' => 'The room is not available for that time. Choose a shorter change or keep the original time.']);
            }

            $amount = BookingTimeAdjustment::quoteAmount($locked->loadMissing('room'), $hours);
            $expiresAt = now()->addMinutes(Booking::holdMinutes());
            $block = RoomBlock::create([
                'room_id' => $room->id, 'status' => 'adjustment_hold', 'starts_at' => $holdStart,
                'ends_at' => $holdEnd, 'hold_expires_at' => $expiresAt,
                'notes' => 'Temporary hold for a pending booking time change.', 'created_by' => $actorId,
            ]);

            return BookingTimeAdjustment::create([
                'booking_id' => $locked->id, 'room_block_id' => $block->id, 'created_by' => $actorId,
                'type' => $type, 'hours' => $hours, 'amount' => $amount,
                'previous_start' => $currentStart, 'previous_end' => $currentEnd,
                'proposed_start' => $proposedStart, 'proposed_end' => $proposedEnd,
                'status' => 'pending', 'expires_at' => $expiresAt,
            ]);
        });
    }

    public function startPayMongo(BookingTimeAdjustment $adjustment, PayMongoCheckout $checkout): string
    {
        $adjustment->loadMissing('booking.room', 'booking.user');
        try {
            $session = $checkout->create($adjustment->booking, 'time-adjustment-'.$adjustment->id, $adjustment);
        } catch (\Throwable $exception) {
            $this->cancel($adjustment, 'Online payment could not be started.');
            throw $exception;
        }

        $payment = Payment::create([
            'booking_id' => $adjustment->booking_id, 'amount' => $adjustment->amount, 'method' => 'gcash',
            'status' => 'pending', 'reference' => 'paymongo:'.$session['id'],
            'notes' => 'PayMongo GCash payment for an approved-time change.',
        ]);
        $adjustment->update(['payment_id' => $payment->id]);

        return $session['url'];
    }

    public function recordCashReceived(BookingTimeAdjustment $adjustment, int $actorId): void
    {
        DB::transaction(function () use ($adjustment, $actorId) {
            $locked = BookingTimeAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($locked->booking_id)->lockForUpdate()->firstOrFail();
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            abort_if($locked->status !== 'pending', 422, 'This time change is no longer waiting for payment.');
            abort_if(! $locked->expires_at || $locked->expires_at->lte(now()), 422, 'The time hold expired. Start the change again so availability can be checked.');
            abort_if(! $this->bookingStillMatchesAdjustment($locked, $booking), 422, 'This booking changed while the time adjustment was being prepared. Please start again.');
            $this->assertHeldIntervalIsAvailable($locked, $booking);

            $payment = Payment::create([
                'booking_id' => $booking->id, 'amount' => $locked->amount, 'method' => 'cash',
                'status' => 'paid', 'paid_at' => now(), 'recorded_by' => $actorId,
                'notes' => 'Staff confirmed cash received for a booking time change.',
            ]);
            $locked->update(['payment_id' => $payment->id]);
            $this->apply($locked, $booking, $actorId);
        });
    }

    public function recordPayMongoPaid(Booking $booking, Payment $payment, ?int $actorId = null): bool
    {
        return DB::transaction(function () use ($booking, $payment, $actorId): bool {
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Room::whereKey($lockedBooking->room_id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $adjustment = BookingTimeAdjustment::where('payment_id', $lockedPayment->id)->lockForUpdate()->first();
            if (! $adjustment || $lockedPayment->status !== 'pending') {
                return false;
            }

            $lockedPayment->update(['status' => 'paid', 'paid_at' => now(), 'notes' => 'GCash time-change payment confirmed by PayMongo.']);
            if ($adjustment->status !== 'pending' || ! $adjustment->expires_at || $adjustment->expires_at->lte(now())) {
                $this->needsReview($adjustment, 'Payment arrived after the temporary room-time hold expired. Staff must contact the guest.');

                return true;
            }
            if (! $this->bookingStillMatchesAdjustment($adjustment, $lockedBooking)) {
                $this->needsReview($adjustment, 'The booking changed before payment arrived. Staff must contact the guest.');

                return true;
            }

            try {
                $this->assertHeldIntervalIsAvailable($adjustment, $lockedBooking);
            } catch (ValidationException) {
                $this->needsReview($adjustment, 'The room time is no longer available. Staff must contact the guest.');

                return true;
            }

            $this->apply($adjustment, $lockedBooking, $actorId);

            return true;
        });
    }

    public function cancel(BookingTimeAdjustment $adjustment, string $reason = 'Payment cancelled.'): void
    {
        DB::transaction(function () use ($adjustment, $reason) {
            $locked = BookingTimeAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                return;
            }
            $locked->update(['status' => 'cancelled', 'resolution_note' => $reason]);
            if ($locked->room_block_id) {
                RoomBlock::whereKey($locked->room_block_id)->update(['completed_at' => now()]);
            }
        });
    }

    private function assertHeldIntervalIsAvailable(BookingTimeAdjustment $adjustment, Booking $booking): void
    {
        $start = $adjustment->type === 'early_checkin' ? $adjustment->proposed_start : $adjustment->previous_end;
        $end = $adjustment->type === 'early_checkin' ? $adjustment->previous_start : $adjustment->proposed_end;
        $room = Room::findOrFail($booking->room_id);
        $conflict = $room->bookings()->whereKeyNot($booking->id)->blocking()->overlapping($start, $end)->exists()
            || $room->blocks()->whereKeyNot($adjustment->room_block_id)->overlapping($start, $end)->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['hours' => 'The room is no longer available for that time.']);
        }
    }

    private function bookingStillMatchesAdjustment(BookingTimeAdjustment $adjustment, Booking $booking): bool
    {
        if ($booking->status !== 'confirmed' || $booking->checked_out_at) {
            return false;
        }
        if ($adjustment->type === 'early_checkin') {
            $currentStart = $booking->check_in_at ?? $booking->check_in->copy()->startOfDay();

            return ! $booking->checked_in_at && $currentStart->equalTo($adjustment->previous_start);
        }
        $currentEnd = $booking->check_out_at ?? $booking->check_out->copy()->startOfDay();

        return $currentEnd->equalTo($adjustment->previous_end);
    }

    private function apply(BookingTimeAdjustment $adjustment, Booking $booking, ?int $actorId): void
    {
        if ($adjustment->room_block_id) {
            RoomBlock::whereKey($adjustment->room_block_id)->update(['completed_at' => now()]);
        }
        $hours = (int) ($booking->hours ?? 0) + (int) $adjustment->hours;
        $attributes = [
            'hours' => $hours, 'nights' => max(1, (int) ceil($hours / 24)),
            'total_amount' => round((float) $booking->total_amount + (float) $adjustment->amount, 2),
        ];
        if ($adjustment->type === 'early_checkin') {
            $attributes['check_in_at'] = $adjustment->proposed_start;
            $attributes['check_in'] = $adjustment->proposed_start->toDateString();
            $attributes['checked_in_at'] = now();
        } else {
            $attributes['check_out_at'] = $adjustment->proposed_end;
            $attributes['check_out'] = $adjustment->proposed_end->toDateString();
        }
        $booking->update($attributes);
        $adjustment->update(['status' => 'applied', 'applied_at' => now()]);
        ActivityLog::create([
            'booking_id' => $booking->id, 'user_id' => $actorId,
            'event' => $adjustment->type === 'early_checkin' ? 'early_checkin_paid' : 'checkout_time_added',
            'description' => $adjustment->type === 'early_checkin'
                ? 'Guest checked in early after the additional payment was confirmed.'
                : 'Additional checkout time was applied after payment was confirmed.',
            'before_values' => ['check_in_at' => $adjustment->previous_start, 'check_out_at' => $adjustment->previous_end],
            'after_values' => ['check_in_at' => $booking->check_in_at, 'check_out_at' => $booking->check_out_at, 'total_amount' => $booking->total_amount],
        ]);
        $eventType = $adjustment->type;
        DB::afterCommit(function () use ($booking, $eventType) {
            $fresh = $booking->fresh(['room', 'user']);
            $email = $fresh->guest_email ?? $fresh->user?->email;
            if (! $email) {
                return;
            }
            $message = $eventType === 'early_checkin'
                ? 'Your early check-in is confirmed. Your stay now starts at '.$fresh->check_in_at->format('M j, g:i A').'.'
                : 'Your checkout time is now '.$fresh->check_out_at->format('M j, g:i A').'.';
            try {
                Mail::to($email)->send(new BookingUpdate($fresh, 'Your Carolina stay time was updated', $message));
            } catch (\Throwable $exception) {
                report($exception);
            }
        });
    }

    private function needsReview(BookingTimeAdjustment $adjustment, string $note): void
    {
        $adjustment->update(['status' => 'needs_review', 'resolution_note' => $note]);
        if ($adjustment->room_block_id) {
            RoomBlock::whereKey($adjustment->room_block_id)->update(['completed_at' => now()]);
        }
    }
}
