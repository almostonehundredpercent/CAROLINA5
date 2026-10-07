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

        $photos = [
            'carolina-room-7' => '/images/rooms/room-7-angle-1.jpg',
            'carolina-room-10' => '/images/rooms/room-10-angle-1.jpg',
            'carolina-room-11' => '/images/rooms/room-11-angle-1.jpg',
            'carolina-room-13' => '/images/rooms/room-13-angle-1.jpg',
        ];
        $amenitiesByRoom = [
            'carolina-room-7' => ['Common CR', 'Air conditioning'],
            'carolina-room-10' => ['Common CR', 'Refrigerator', 'Air conditioning'],
            'carolina-room-11' => ['Private CR', 'Refrigerator', 'Air conditioning'],
            'carolina-room-13' => ['Private CR', 'Air conditioning'],
        ];

        DB::transaction(function () use ($photos, $amenitiesByRoom): void {
            $slugs = array_unique(array_merge(array_keys($photos), array_keys($amenitiesByRoom)));
            $rooms = DB::table('rooms')->whereIn('slug', $slugs)->lockForUpdate()->get();

            foreach ($rooms as $room) {
                $changes = ['updated_at' => now()];

                if (isset($photos[$room->slug])) {
                    $changes['image_url'] = $photos[$room->slug];
                }

                if (isset($amenitiesByRoom[$room->slug])) {
                    $amenities = json_decode((string) $room->amenities, true);
                    $amenities = is_array($amenities) ? $amenities : [];
                    $amenities = array_values(array_filter($amenities, function ($amenity): bool {
                        if (! is_string($amenity)) {
                            return true;
                        }

                        $amenity = strtolower(trim($amenity));

                        return ! preg_match('/\b(?:(?:common|private|shared)\s*)?cr\b|\b(?:bathroom|toilet)\b/i', $amenity)
                            && ! preg_match('/\b(?:a\/c|ac|air[-\s]condition(?:ed|er|ing)?)\b/i', $amenity)
                            && ! preg_match('/\b(?:ref|refrigerator|fridge)\b/i', $amenity);
                    }));

                    $changes['amenities'] = json_encode(
                        array_values(array_unique(array_merge($amenities, $amenitiesByRoom[$room->slug]))),
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
                    );
                }

                DB::table('rooms')->where('id', $room->id)->update($changes);
            }
        });
    }

    public function down(): void
    {
        // These are room-content updates. Avoid overwriting later staff edits
        // with guessed values when rolling back application code.
    }
};
