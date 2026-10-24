<?php

namespace App\Services;

use App\Mail\BookingUpdate;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingTimeAdjustment;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PayMongoPaymentReconciler
{
    /** Confirm that a checkout belongs to this booking and represents its full PHP payment. */
    public function isMatchingPaidCheckout(Booking $booking, Payment $payment, array $checkout): bool
    {
        $checkoutId = substr((string) $payment->reference, strlen('paymongo:'));
        $attributes = data_get($checkout, 'data.attributes', []);
        $expectedLiveMode = config('services.paymongo.mode') === 'live';
        $payments = data_get($attributes, 'payments', []);

        if ($checkoutId === ''
            || data_get($checkout, 'data.id') !== $checkoutId
            || data_get($attributes, 'reference_number') !== $booking->reference
            || data_get($attributes, 'livemode') !== $expectedLiveMode
            || ! is_array($payments)) {
            return false;
        }

        $expectedAmount = (int) round(((float) $payment->amount) * 100);
        $adjustment = BookingTimeAdjustment::where('payment_id', $payment->id)->first();
        $expectedCheckoutAmount = $adjustment
            ? (int) round(((float) $adjustment->amount) * 100)
            : (int) round(((float) $booking->total_amount) * 100);
        if ($expectedAmount !== $expectedCheckoutAmount) {
            return false;
        }

        foreach ($payments as $providerPayment) {
            $providerAttributes = data_get($providerPayment, 'attributes', []);
            if (data_get($providerAttributes, 'status') === 'paid'
                && data_get($providerAttributes, 'currency') === 'PHP'
                && (int) data_get($providerAttributes, 'amount', -1) === $expectedAmount) {
                return true;
            }
        }

        return false;
    }

    /** Record a verified full payment and confirm its available booking exactly once. */
    public function recordPaid(Booking $booking, Payment $payment, ?int $actorId = null): bool
    {
        if (BookingTimeAdjustment::where('payment_id', $payment->id)->exists()) {
            return app(BookingTimeAdjustmentService::class)->recordPayMongoPaid($booking, $payment, $actorId);
        }

        $changed = DB::transaction(function () use ($booking, $payment, $actorId): bool {
            $room = Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->method !== 'gcash'
                || ! str_starts_with((string) $lockedPayment->reference, 'paymongo:')
                || $lockedPayment->status !== 'pending') {
                return false;
            }

            $expired = $lockedBooking->hasExpiredHold();
            $conflict = false;
            if ($lockedBooking->status === 'pending' && ! $expired) {
                $startsAt = $lockedBooking->check_in_at ?? $lockedBooking->check_in->copy()->startOfDay();
                $endsAt = $lockedBooking->check_out_at ?? $lockedBooking->check_out->copy()->startOfDay();
                $conflict = $room->bookings()->whereKeyNot($lockedBooking->id)->blocking()->overlapping($startsAt, $endsAt)->exists()
                    || $room->blocks()->overlapping($startsAt, $endsAt)->exists();
            }
            $lockedPayment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'notes' => 'Full GCash payment confirmed by PayMongo.',
            ]);

            $updates = [
                'payment_method' => 'gcash',
                'payment_status' => 'paid',
                'paid_at' => now(),
            ];
            if (($expired || $conflict) && $lockedBooking->status === 'pending') {
                $updates += [
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $expired
                        ? 'Payment was confirmed after the reservation hold expired; staff resolution required.'
                        : 'Payment was confirmed after the room became unavailable; staff resolution required.',
                ];
            } elseif ($lockedBooking->status === 'pending') {
                $updates += [
                    'status' => 'confirmed',
                    'hold_expires_at' => null,
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                ];
            }
            $lockedBooking->update($updates);

            ActivityLog::create([
                'booking_id' => $lockedBooking->id,
                'user_id' => $actorId,
                'event' => 'payment_paid',
                'description' => 'Full GCash payment confirmed by PayMongo.',
                'before_values' => ['payment_status' => 'pending'],
                'after_values' => ['payment_status' => 'paid', 'payment_method' => 'gcash', 'status' => $lockedBooking->status],
            ]);

            if ($lockedBooking->status === 'confirmed' && $lockedBooking->wasChanged('status')) {
                ActivityLog::create([
                    'booking_id' => $lockedBooking->id,
                    'user_id' => $actorId,
                    'event' => 'booking_auto_confirmed',
                    'description' => 'Booking automatically confirmed after full PayMongo payment and availability check.',
                    'before_values' => ['status' => 'pending'],
                    'after_values' => ['status' => 'confirmed'],
                ]);
            }

            return true;
        });

        if ($changed) {
            $booking->refresh()->loadMissing('user');
            $message = $booking->status === 'cancelled'
                ? 'PayMongo confirmed your GCash payment, but the reservation is no longer active. Carolina staff will contact you to resolve the booking.'
                : 'PayMongo confirmed your full GCash payment and your reservation is confirmed.';
            $email = $booking->guest_email ?? $booking->user?->email;

            if ($email) {
                try {
                    Mail::to($email)->send(new BookingUpdate($booking, 'Your Carolina payment was confirmed', $message));
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        return $changed;
    }
}
