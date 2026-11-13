<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ROOM_SLUG = 'whole-house-panal-20-guests';

    public function up(): void
    {
        DB::table('rooms')->where('slug', self::ROOM_SLUG)->update([
            'guests' => 6,
            'description' => 'A double-deck room for up to six guests, with a starting rate of ₱450 for a 6-hour air-conditioned stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->where('slug', self::ROOM_SLUG)->update([
            'guests' => 4,
            'description' => 'A double-deck room for up to four guests, with a starting rate of ₱450 for a 6-hour air-conditioned stay.',
            'updated_at' => now(),
        ]);
    }
};
