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

        DB::table('rooms')->where('slug', 'carolina-room-11')->update([
            'room_type' => 'Fan room',
            'beds' => 1,
            'guests' => 4,
            'price_per_night' => 450,
            'rate_label' => '',
            'rental_hours' => 22,
            'description' => 'A comfortable fan room with one bed for up to four guests. Prices start at ₱450 for a 22-hour stay beginning at noon.',
            'image_url' => '/images/rooms/room-11-angle-3.jpg',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve current room details when rolling back application code.
    }
};
