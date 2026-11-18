<?php

namespace App\Services;

use App\Mail\CheckInReminder;
use App\Models\Booking;
use App\Support\PropertyTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckInReminderService
{
    private const LEAD_MINUTES = 20;

    public function sendDueReminders(): int
    {
        $now = PropertyTime::now();
        $dueBy = $now->copy()->addMinutes(self::LEAD_MINUTES);
        $sent = 0;

        Booking::query()
            ->where('status', 'confirmed')
            ->whereNotNull('check_in_at')
            ->where('check_in_at', '>', $now)
            ->where('check_in_at', '<=', $dueBy)
            ->whereNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->whereNull('check_in_reminder_sent_at')
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
            $now = PropertyTime::now();
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->where('status', 'confirmed')
                ->whereNotNull('check_in_at')
                ->where('check_in_at', '>', $now)
                ->where('check_in_at', '<=', $now->copy()->addMinutes(self::LEAD_MINUTES))
                ->whereNull('checked_in_at')
                ->whereNull('checked_out_at')
                ->whereNull('check_in_reminder_sent_at')
                ->with(['user', 'room'])
                ->lockForUpdate()
                ->first();

            if (! $booking) {
                return false;
            }

            $email = trim((string) ($booking->guest_email ?: $booking->user?->email));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            Mail::to($email)->queue(
                (new CheckInReminder($booking))
                    ->onConnection('database')
                    ->onQueue('mail')
            );

            $booking->update(['check_in_reminder_sent_at' => $now]);

            return true;
        });
    }
}
