<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('rooms')->get(['id', 'amenities']) as $room) {
            $amenities = is_array($room->amenities)
                ? $room->amenities
                : json_decode($room->amenities ?? '[]', true);
            $amenities = is_array($amenities) ? $amenities : [];
            $filtered = array_values(array_filter($amenities, fn ($amenity) => ! is_string($amenity)
                || ! preg_match('/\bkitchen\s*wares?\b/i', $amenity)));

            if (count($filtered) !== count($amenities)) {
                DB::table('rooms')->where('id', $room->id)->update([
                    'amenities' => json_encode($filtered),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Removed amenity values cannot be safely restored because they varied by room.
    }
};
