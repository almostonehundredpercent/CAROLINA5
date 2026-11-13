<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'fan-room-solo')->update([
            'image_url' => '/images/rooms/room-1-angle-1.png',
            'amenities' => json_encode(['Common CR'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->where('slug', 'fan-room-solo')->update([
            'image_url' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80',
            'amenities' => json_encode(['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit'], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }
};
