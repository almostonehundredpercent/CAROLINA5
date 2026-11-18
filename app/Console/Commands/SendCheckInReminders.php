<?php

namespace App\Console\Commands;

use App\Services\CheckInReminderService;
use Illuminate\Console\Command;

class SendCheckInReminders extends Command
{
    protected $signature = 'bookings:send-check-in-reminders';

    protected $description = 'Send one email reminder shortly before a confirmed booking check-in';

    public function handle(CheckInReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();
        $this->info("Queued {$sent} check-in reminder(s).");

        return self::SUCCESS;
    }
}
