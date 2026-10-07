<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $profiles = [];

        foreach ([
            ['fan-room-solo', 'Fan room', 1, 1, 450, 'per 22-hour stay', 22, 'A simple private fan room for one guest, with a 22-hour stay beginning at noon.', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            ['air-conditioned-room-couple', 'Air-conditioned room', 1, 2, 450, 'per 6-hour stay', 6, 'A cool, comfortable air-conditioned room for two guests on a short 6-hour stay.', 'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            ['fan-room-couple', 'Fan room', 1, 2, 699, 'per 12-hour stay', 12, 'A practical fan room for two, offered as a 12-hour daytime stay.', 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            ['air-conditioned-day-stay', 'Air-conditioned room', 1, 2, 799, 'per 12-hour stay', 12, 'An air-conditioned room for two with a 12-hour daytime stay from 8 AM to 8 PM.', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            ['whole-house-panal-10-guests', 'Whole house', 3, 10, 4500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch, ideal for families and groups of up to 10.', 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1000&q=80', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
            ['whole-house-panal-20-guests', 'Whole house', 3, 20, 5500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch for larger groups of up to 20 guests.', 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
        ] as $index => [$slug, $type, $beds, $guests, $price, $rateLabel, $rentalHours, $description, $image, $amenities]) {
            $profiles[] = Room::updateOrCreate(['slug' => $slug], [
                'name' => 'Room '.($index + 1), 'room_type' => $type, 'beds' => $beds, 'guests' => $guests,
                'price_per_night' => $price, 'rate_label' => $rateLabel, 'rental_hours' => $rentalHours,
                'description' => $description, 'image_url' => $image, 'amenities' => $amenities, 'is_active' => true,
            ]);
        }

        // Keep any existing units and history intact; only fill missing records.
        // Each original room profile is assigned twice across rooms 7–18.
        $assignments = array_merge($profiles, $profiles);
        shuffle($assignments);

        foreach (range(7, 18) as $offset => $number) {
            $slug = 'carolina-room-'.$number;
            $room = Room::firstOrNew(['slug' => $slug]);
            $isNew = ! $room->exists;
            $room->name = 'Room '.$number;
            $room->is_active = true;

            if ($isNew) {
                $profile = $assignments[$offset];
                $room->fill($profile->only([
                    'room_type', 'beds', 'guests', 'price_per_night', 'rate_label',
                    'rental_hours', 'description', 'image_url', 'amenities',
                    'default_check_in_time',
                ]));
                $room->operational_status = 'available';
                $room->operational_until = null;
            }

            $room->save();

            if ($isNew) {
                $room->promoCodes()->syncWithoutDetaching($assignments[$offset]->promoCodes()->pluck('promo_codes.id')->all());
            }
        }
    }
}
