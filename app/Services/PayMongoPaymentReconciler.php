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
    /** Confirm that a checkout belongs to this booking and matches its recorded PHP amount. */
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
        $expectedCheckoutAmount = (int) round(((float) ($adjustment?->amount ?? $payment->amount)) * 100);
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

    /** Record a verified payment and confirm only when the full booking value is paid. */
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
            $paidAt = now();
            $lockedPayment->update([
                'status' => 'paid',
                'paid_at' => $paidAt,
                'notes' => (float) $lockedBooking->deposit_amount > 0 && (float) $lockedPayment->amount < (float) $lockedBooking->total_amount
                    ? 'GCash downpayment confirmed by PayMongo.'
                    : 'GCash payment confirmed by PayMongo.',
            ]);

            $paidTotal = (float) Payment::where('booking_id', $lockedBooking->id)->where('status', 'paid')->sum('amount');
            $refundedTotal = (float) Payment::where('booking_id', $lockedBooking->id)->where('status', 'refunded')->sum('amount');
            $netCollected = max(0, $paidTotal - $refundedTotal);
            $depositTarget = (float) $lockedBooking->deposit_amount > 0
                ? min((float) $lockedBooking->deposit_amount, (float) $lockedBooking->total_amount)
                : (float) $lockedBooking->total_amount;
            $depositVerified = $netCollected + 0.001 >= $depositTarget;
            $fullyPaid = $netCollected + 0.001 >= (float) $lockedBooking->total_amount;

            $updates = [
                'payment_method' => 'gcash',
                'payment_status' => $fullyPaid ? 'paid' : ($netCollected > 0 ? 'partial' : 'pending'),
                'paid_at' => $fullyPaid ? $paidAt : null,
            ];
            if ((float) $lockedBooking->deposit_amount > 0) {
                $updates['deposit_status'] = $depositVerified ? 'verified' : 'awaiting_payment';
                if ($depositVerified && ! $lockedBooking->deposit_verified_at) {
                    $updates['deposit_verified_at'] = $paidAt;
                    $updates['deposit_verified_by'] = $actorId;
                }
                if ($depositVerified) {
                    $updates['deposit_due_at'] = null;
                }
                if ($depositVerified && ! $fullyPaid) {
                    $updates['hold_expires_at'] = null;
                }
            }
            if (($expired || $conflict) && $lockedBooking->status === 'pending') {
                $updates += [
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => $expired
                        ? 'Payment was confirmed after the reservation hold expired; staff resolution required.'
                        : 'Payment was confirmed after the room became unavailable; staff resolution required.',
                ];
            } elseif ($lockedBooking->status === 'pending' && $fullyPaid) {
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
                'description' => $fullyPaid ? 'Full GCash payment confirmed by PayMongo.' : 'GCash downpayment confirmed by PayMongo; booking remains pending staff review.',
                'before_values' => ['payment_status' => 'pending'],
                'after_values' => ['payment_status' => $lockedBooking->payment_status, 'deposit_status' => $lockedBooking->deposit_status, 'payment_method' => 'gcash', 'status' => $lockedBooking->status],
            ]);

            if ($fullyPaid && $lockedBooking->status === 'confirmed' && $lockedBooking->wasChanged('status')) {
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
            $financial = \App\Support\FinancialSummary::forBooking($booking->load('payments'));
            $message = $booking->status === 'cancelled'
                ? 'PayMongo confirmed your GCash payment, but the reservation is no longer active. Carolina staff will contact you to resolve the booking.'
                : ($booking->payment_status === 'paid'
                    ? 'PayMongo confirmed your full GCash payment and your reservation is confirmed.'
                    : 'PayMongo confirmed your downpayment of ₱'.number_format((float) $payment->amount, 2).'. Your booking is protected while Carolina staff reviews it. Remaining balance: ₱'.number_format($financial['outstanding'], 2).'.');
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
