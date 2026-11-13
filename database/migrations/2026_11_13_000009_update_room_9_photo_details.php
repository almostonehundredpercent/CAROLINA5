<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $room = DB::table('rooms')->where('slug', 'carolina-room-9')->first(['amenities']);
        if (! $room) {
            return;
        }

        $amenities = is_array($room->amenities)
            ? $room->amenities
            : json_decode($room->amenities ?? '[]', true);
        $amenities = is_array($amenities) ? $amenities : [];
        $amenities = array_values(array_filter($amenities, fn ($amenity) => ! is_string($amenity)
            || ! in_array(strtolower(trim($amenity)), ['complete kitchen wares', 'large refrigerator'], true)));

        DB::table('rooms')->where('slug', 'carolina-room-9')->update([
            'image_url' => '/images/rooms/room-9-angle-1.png',
            'description' => 'A comfortable room for up to two guests, with a simple, welcoming setup and a relaxed place to unwind during your stay.',
            'guests' => 2,
            'amenities' => json_encode($amenities),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The prior profile, image, capacity, and amenities vary because numbered rooms inherit randomized profiles.
    }
};
