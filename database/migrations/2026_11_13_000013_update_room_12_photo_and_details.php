<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'carolina-room-12')->update([
            'image_url' => '/images/rooms/room-12-angle-1.png',
            'description' => 'A comfortable room with one bed for up to four guests, offering a simple and welcoming place to rest during your stay.',
            'beds' => 1,
            'guests' => 4,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Room 12 previously inherited randomized profile details, so the prior values are not reliable.
    }
};
