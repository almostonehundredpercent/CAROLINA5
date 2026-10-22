<?php

namespace App\Services;

use App\Mail\BookingUpdate;
use App\Models\ActivityLog;
use App\Models\Booking;
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
        $bookingAmount = (int) round(((float) $booking->total_amount) * 100);
        if ($expectedAmount !== $bookingAmount) {
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

    /** Mark one pending PayMongo ledger row paid once, even if return and webhook race. */
    public function recordPaid(Booking $booking, Payment $payment, ?int $actorId = null): bool
    {
        $changed = DB::transaction(function () use ($booking, $payment, $actorId): bool {
            Room::whereKey($booking->room_id)->lockForUpdate()->first();
            $lockedBooking = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($lockedPayment->method !== 'gcash'
                || ! str_starts_with((string) $lockedPayment->reference, 'paymongo:')
                || $lockedPayment->status !== 'pending') {
                return false;
            }

            $expired = $lockedBooking->hasExpiredHold();
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
            if ($expired && $lockedBooking->status !== 'cancelled') {
                $updates += [
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Payment was confirmed after the reservation hold expired; staff resolution required.',
                ];
            }
            $lockedBooking->update($updates);

            ActivityLog::create([
                'booking_id' => $lockedBooking->id,
                'user_id' => $actorId,
                'event' => 'payment_paid',
                'description' => 'Full GCash payment confirmed by PayMongo.',
                'before_values' => ['payment_status' => 'pending'],
                'after_values' => ['payment_status' => 'paid', 'payment_method' => 'gcash'],
            ]);

            return true;
        });

        if ($changed) {
            $booking->refresh()->loadMissing('user');
            $message = $booking->status === 'cancelled'
                ? 'PayMongo confirmed your GCash payment, but the reservation is no longer active. Carolina staff will contact you to resolve the booking.'
                : 'PayMongo confirmed your GCash payment. Carolina staff will still review your reservation.';
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
