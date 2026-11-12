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
            'description' => 'A comfortable air-conditioned room with one bed for up to two guests and access to a shared bathroom.',
            'updated_at' => now(),
        ]);

        DB::table('rooms')->where('slug', 'fan-room-couple')->update([
            'price_per_night' => 450,
            'rate_label' => 'per 22-hour stay',
            'rental_hours' => 22,
            'description' => 'A practical fan room for up to two guests, with rates starting at ₱450 for a 22-hour stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'air-conditioned-room-couple')->update([
            'description' => 'A comfortable air-conditioned room with one bed for up to two guests and access to a shared bathroom. Price starts at ₱450.',
            'updated_at' => now(),
        ]);

        // Room 3's new 22-hour rate was already established by migration 000014.
    }
};
