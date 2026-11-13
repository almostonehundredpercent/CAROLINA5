<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'whole-house-panal-20-guests')->update([
            'image_url' => '/images/rooms/room-6-angle-1.png',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->where('slug', 'whole-house-panal-20-guests')->update([
            'image_url' => 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80',
            'updated_at' => now(),
        ]);
    }
};
