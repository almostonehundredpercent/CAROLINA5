<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'air-conditioned-day-stay')->update([
            'room_type' => 'Air-conditioned room',
            'beds' => 1,
            'guests' => 2,
            'price_per_night' => 450,
            'rate_label' => '',
            'rental_hours' => 22,
            'description' => 'Best suited to solo travelers, this air-conditioned room has one bed for up to two guests. 22-hour stays start at ₱450.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the current guest-facing Room 4 details on rollback.
    }
};
