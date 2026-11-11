<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roomDetails = [
        'air-conditioned-room-couple' => [
            'image_url' => '/images/rooms/room-2-angle-1.jpg',
            'amenities' => ['AC/Fan', 'Common CR'],
        ],
        'fan-room-couple' => [
            'image_url' => '/images/rooms/room-3-angle-1.jpg',
            'amenities' => ['AC/Fan', 'With CR', 'Refrigerator', 'TV'],
        ],
        'air-conditioned-day-stay' => [
            'image_url' => '/images/rooms/room-4-angle-1.jpg',
            'amenities' => ['AC/Fan', 'Common CR'],
        ],
        'whole-house-panal-10-guests' => [
            'image_url' => '/images/rooms/room-5-angle-1.jpg',
            'amenities' => ['AC/Fan', 'Common CR', 'TV in room'],
        ],
    ];

    private array $previousDetails = [
        'air-conditioned-room-couple' => [
            'image_url' => 'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=1000&q=80',
            'amenities' => ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit'],
        ],
        'fan-room-couple' => [
            'image_url' => 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=1000&q=80',
            'amenities' => ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit'],
        ],
        'air-conditioned-day-stay' => [
            'image_url' => 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1000&q=80',
            'amenities' => ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit'],
        ],
        'whole-house-panal-10-guests' => [
            'image_url' => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1000&q=80',
            'amenities' => ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking'],
        ],
    ];

    public function up(): void
    {
        $this->apply($this->roomDetails);
    }

    public function down(): void
    {
        $this->apply($this->previousDetails);
    }

    private function apply(array $rooms): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::transaction(function () use ($rooms): void {
            foreach ($rooms as $slug => $details) {
                DB::table('rooms')->where('slug', $slug)->update([
                    'image_url' => $details['image_url'],
                    'amenities' => json_encode($details['amenities'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
