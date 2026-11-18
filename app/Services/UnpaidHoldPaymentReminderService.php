<?php

namespace App\Services;

use App\Mail\UnpaidHoldPaymentReminder;
use App\Models\Booking;
use App\Models\DecisionSupportSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class UnpaidHoldPaymentReminderService
{
    private const LEAD_MINUTES = 5;

    public function sendDueReminders(): int
    {
        if (! DecisionSupportSetting::find(1)?->unpaid_hold_reminders_enabled) {
            return 0;
        }

        $sent = 0;

        Booking::unpaidUnconfirmed()
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '>', now())
            ->where('hold_expires_at', '<=', now()->addMinutes(self::LEAD_MINUTES))
            ->whereNull('payment_reminder_sent_at')
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$sent): void {
                foreach ($bookings as $booking) {
                    if ($this->sendIfStillDue((int) $booking->id)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function sendIfStillDue(int $bookingId): bool
    {
        return DB::transaction(function () use ($bookingId): bool {
            $booking = Booking::unpaidUnconfirmed()
                ->whereKey($bookingId)
                ->whereNotNull('hold_expires_at')
                ->where('hold_expires_at', '>', now())
                ->where('hold_expires_at', '<=', now()->addMinutes(self::LEAD_MINUTES))
                ->whereNull('payment_reminder_sent_at')
                ->with(['user', 'room'])
                ->lockForUpdate()
                ->first();

            if (! $booking || ! DecisionSupportSetting::find(1)?->unpaid_hold_reminders_enabled) {
                return false;
            }

            $email = trim((string) ($booking->guest_email ?: $booking->user?->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            Mail::to($email)->queue(
                (new UnpaidHoldPaymentReminder($booking))
                    ->onConnection('database')
                    ->onQueue('mail')
            );

            $booking->update(['payment_reminder_sent_at' => now()]);

            return true;
        });
    }
}
