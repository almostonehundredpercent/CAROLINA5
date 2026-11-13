<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROOM_SLUG = 'whole-house-panal-20-guests';

    public function up(): void
    {
        DB::table('rooms')->where('slug', self::ROOM_SLUG)->update([
            'room_type' => 'Double-deck room',
            'beds' => 2,
            'guests' => 4,
            'price_per_night' => 450,
            'rate_label' => 'starting price',
            'rental_hours' => 6,
            'description' => 'A double-deck room for up to four guests, with a starting rate of ₱450 for a 6-hour air-conditioned stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->where('slug', self::ROOM_SLUG)->update([
            'room_type' => 'Whole house',
            'beds' => 3,
            'guests' => 20,
            'price_per_night' => 5500,
            'rate_label' => 'per night',
            'rental_hours' => null,
            'description' => 'A three-bedroom whole-house stay at the Panal branch for larger groups of up to 20 guests.',
            'updated_at' => now(),
        ]);
    }
};
