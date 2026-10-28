<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookingHoldExpiryService
{
    public function __construct(
        private readonly PayMongoCheckout $checkout,
        private readonly PayMongoPaymentReconciler $payments,
    ) {}

    /** Expire all eligible unpaid holds and return the number finalized in this run. */
    public function expireExpiredHolds(): int
    {
        $finalized = 0;

        Booking::unpaidUnconfirmed()
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$finalized): void {
                foreach ($bookings as $booking) {
                    if ($this->expireBooking($booking)) {
                        $finalized++;
                    }
                }
            });

        return $finalized;
    }

    private function expireBooking(Booking $booking): bool
    {
        $pendingPayments = $booking->payments()
            ->where('method', 'gcash')
            ->where('status', 'pending')
            ->where('reference', 'like', 'paymongo:%')
            ->orderBy('id')
            ->get();

        foreach ($pendingPayments as $payment) {
            $checkoutId = substr((string) $payment->reference, strlen('paymongo:'));
            if ($checkoutId === '') {
                continue;
            }

            try {
                $providerSession = $this->checkout->expire($checkoutId);
            } catch (Throwable $exception) {
                Log::notice('PayMongo checkout expiry will be retried.', [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'exception' => $exception::class,
                ]);

                try {
                    $providerSession = $this->checkout->retrieve($checkoutId);
                } catch (Throwable $lookupException) {
                    Log::notice('PayMongo checkout status could not be checked during hold expiry.', [
                        'booking_id' => $booking->id,
                        'payment_id' => $payment->id,
                        'exception' => $lookupException::class,
                    ]);

                    continue;
                }
            }

            if ($this->payments->isMatchingPaidCheckout($booking, $payment, $providerSession)) {
                $this->payments->recordPaid($booking, $payment);

                continue;
            }

            $providerStatus = strtolower((string) data_get($providerSession, 'data.attributes.status'));
            if (in_array($providerStatus, ['expired', 'failed'], true)) {
                $this->markProviderPaymentFailed($booking, $payment, $providerStatus);

                continue;
            }

            // An active/processing or unrecognized provider state is not final.
            // Leave both records pending so a later scheduled run can retry safely.
            Log::info('PayMongo checkout remains pending after expiry attempt.', [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'provider_status' => $providerStatus ?: 'unknown',
            ]);
        }

        return $this->cancelIfStillUnpaid($booking->id);
    }

    private function markProviderPaymentFailed(Booking $booking, Payment $payment, string $providerStatus): void
    {
        DB::transaction(function () use ($booking, $payment, $providerStatus): void {
            Room::whereKey($booking->room_id)->lockForUpdate()->firstOrFail();
            Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $lockedPayment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($lockedPayment?->status === 'pending') {
                $lockedPayment->update([
                    'status' => 'failed',
                    'notes' => 'PayMongo checkout session '.$providerStatus.'.',
                ]);
            }
        });
    }

    private function cancelIfStillUnpaid(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            $bookingSnapshot = Booking::find($bookingId);
            if (! $bookingSnapshot) {
                return false;
            }

            Room::whereKey($bookingSnapshot->room_id)->lockForUpdate()->firstOrFail();
            $booking = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();

            if (! $booking->hasExpiredHold()
                || $booking->payments()->where('method', 'gcash')->where('status', 'pending')->where('reference', 'like', 'paymongo:%')->exists()) {
                return false;
            }

            $hasPayMongoSession = $booking->payments()
                ->where('method', 'gcash')
                ->where('reference', 'like', 'paymongo:%')
                ->exists();

            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => null,
                'cancellation_reason' => 'Unpaid reservation hold expired.',
                ...($hasPayMongoSession ? ['payment_status' => 'failed'] : []),
            ]);

            ActivityLog::create([
                'booking_id' => $booking->id,
                'event' => 'unpaid_hold_expired',
                'description' => 'Unpaid reservation hold expired; provider checkouts were closed or no checkout had been started.',
                'before_values' => ['status' => 'pending'],
                'after_values' => ['status' => 'cancelled', 'cancellation_reason' => 'Unpaid reservation hold expired.'],
            ]);

            return true;
        });
    }
}
