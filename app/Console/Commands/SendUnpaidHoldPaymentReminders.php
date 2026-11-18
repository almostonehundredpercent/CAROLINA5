<?php

namespace App\Console\Commands;

use App\Services\UnpaidHoldPaymentReminderService;
use Illuminate\Console\Command;

class SendUnpaidHoldPaymentReminders extends Command
{
    protected $signature = 'bookings:send-hold-reminders';

    protected $description = 'Send one payment reminder before an unpaid booking hold expires';

    public function handle(UnpaidHoldPaymentReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();
        $this->info("Queued {$sent} unpaid hold payment reminder(s).");

        return self::SUCCESS;
    }
}
