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

        DB::table('rooms')->where('slug', 'fan-room-couple')->update([
            'price_per_night' => 699,
            'rate_label' => 'per 12-hour stay',
            'rental_hours' => 12,
            'description' => 'A practical fan room for two, offered as a 12-hour daytime stay.',
            'updated_at' => now(),
        ]);
    }
};
