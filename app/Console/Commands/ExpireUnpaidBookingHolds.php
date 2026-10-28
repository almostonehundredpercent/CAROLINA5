<?php

namespace App\Console\Commands;

use App\Services\BookingHoldExpiryService;
use Illuminate\Console\Command;

class ExpireUnpaidBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Expire unpaid booking holds and their abandoned PayMongo checkouts';

    public function handle(BookingHoldExpiryService $expiry): int
    {
        $expired = $expiry->expireExpiredHolds();
        $this->info("Expired {$expired} unpaid reservation hold(s).");

        return self::SUCCESS;
    }
}
