<?php

use App\Models\Booking;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Do not discard old requests on rollout. Give unpaid legacy requests
        // one day for staff review; confirmed/payment-backed records are exempt.
        Booking::unpaidUnconfirmed()->whereNull('hold_expires_at')
            ->update(['hold_expires_at' => now()->addDay()]);
    }

    public function down(): void
    {
        // Keep deadlines: removing them would recreate indefinite holds.
    }
};
