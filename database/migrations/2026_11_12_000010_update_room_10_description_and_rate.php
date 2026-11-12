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

        DB::transaction(function (): void {
            $room = DB::table('rooms')
                ->where('slug', 'carolina-room-10')
                ->lockForUpdate()
                ->first();

            if (! $room) {
                return;
            }

            $amenities = json_decode((string) $room->amenities, true);
            $amenities = is_array($amenities) ? $amenities : [];
            $amenities = array_values(array_filter($amenities, static function ($amenity): bool {
                return ! is_string($amenity) || strtolower(trim($amenity)) !== 'complete kitchen wares';
            }));

            DB::table('rooms')->where('id', $room->id)->update([
                'room_type' => 'Air-conditioned room',
                'beds' => 1,
                'guests' => 2,
                'price_per_night' => 450,
                'rate_label' => 'per night',
                'rental_hours' => null,
                'description' => 'A one-bedroom, air-conditioned stay with one bed for up to two guests. Rates start at ₱450 per night.',
                'amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Preserve current room details when rolling back application code.
    }
};
