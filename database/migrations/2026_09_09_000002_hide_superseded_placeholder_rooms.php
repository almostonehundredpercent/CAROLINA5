<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $catalog = [
            'fan-room-solo' => ['Fan Room — Solo', 'Fan room', 1, 1, 450, 'per 22-hour stay', 22, 'A simple private fan room for one guest, with a 22-hour stay beginning at noon.', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            'air-conditioned-room-couple' => ['Air-conditioned Room — Couple', 'Air-conditioned room', 1, 2, 450, 'per 6-hour stay', 6, 'A cool, comfortable air-conditioned room for two guests on a short 6-hour stay.', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            'fan-room-couple' => ['Fan Room — Couple', 'Fan room', 1, 2, 699, 'per 12-hour stay', 12, 'A practical fan room for two, offered as a 12-hour daytime stay.', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            'air-conditioned-day-stay' => ['Air-conditioned Day Stay', 'Air-conditioned room', 1, 2, 799, 'per 12-hour stay', 12, 'An air-conditioned room for two with a 12-hour daytime stay from 8 AM to 8 PM.', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            'whole-house-panal-10-guests' => ['Whole House — Panal (10 Guests)', 'Whole house', 3, 10, 4500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch, ideal for families and groups of up to 10.', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
            'whole-house-panal-20-guests' => ['Whole House — Panal (20 Guests)', 'Whole house', 3, 20, 5500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch for larger groups of up to 20 guests.', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
        ];

        foreach ($catalog as $slug => [$name, $type, $beds, $guests, $price, $label, $hours, $description, $amenities]) {
            DB::table('rooms')->where('slug', $slug)->update([
                'name' => $name, 'room_type' => $type, 'beds' => $beds, 'guests' => $guests,
                'price_per_night' => $price, 'rate_label' => $label, 'rental_hours' => $hours,
                'description' => $description, 'amenities' => json_encode($amenities), 'is_active' => true, 'updated_at' => now(),
            ]);
        }

        DB::table('rooms')->whereIn('slug', [
            'cozy-standard-room', 'deluxe-queen-room', 'family-comfort-room',
            'garden-view-suite', 'barkada-room', 'executive-studio',
        ])->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('rooms')->whereIn('slug', [
            'cozy-standard-room', 'deluxe-queen-room', 'family-comfort-room',
            'garden-view-suite', 'barkada-room', 'executive-studio',
        ])->update(['is_active' => true, 'updated_at' => now()]);
    }
};
