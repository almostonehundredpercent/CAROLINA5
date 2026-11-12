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

        DB::table('rooms')->where('slug', 'carolina-room-13')->update([
            'room_type' => 'Air-conditioned room',
            'beds' => 1,
            'guests' => 2,
            'price_per_night' => 450,
            'rate_label' => 'per night',
            'rental_hours' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the corrected owner-provided rate and capacity on rollback.
    }
};
