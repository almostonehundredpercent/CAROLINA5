<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'carolina-room-8')->update([
            'image_url' => '/images/rooms/room-8-angle-1.png',
            'description' => 'Ideal for a solo traveler, this one-bed room offers a simple and comfortable place to unwind after a busy day. Its practical layout provides a restful base during your stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The prior image and description can vary because numbered rooms inherit randomized profiles.
    }
};
