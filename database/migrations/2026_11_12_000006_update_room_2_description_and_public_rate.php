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

        DB::table('rooms')->where('slug', 'air-conditioned-room-couple')->update([
            'beds' => 1,
            'guests' => 2,
            'price_per_night' => 450,
            'rate_label' => '',
            'description' => 'A comfortable air-conditioned room with one bed for up to two guests and access to a shared bathroom. Rates start at ₱450.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the guest-facing room details accurate if this migration is rolled back.
    }
};