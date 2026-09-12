<?php
namespace Database\Seeders;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
class RoomSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Fan Room — Solo', 'Fan room', 1, 1, 450, 'per 22-hour stay', 22, 'A simple private fan room for one guest, with a 22-hour stay beginning at noon.', 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            ['Air-conditioned Room — Couple', 'Air-conditioned room', 1, 2, 450, 'per 6-hour stay', 6, 'A cool, comfortable air-conditioned room for two guests on a short 6-hour stay.', 'https://images.unsplash.com/photo-1616594039964-ae9021a400a0?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            ['Fan Room — Couple', 'Fan room', 1, 2, 699, 'per 12-hour stay', 12, 'A practical fan room for two, offered as a 12-hour daytime stay.', 'https://images.unsplash.com/photo-1566665797739-1674de7a421a?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Private CR', 'Towel', 'Hygiene kit']],
            ['Air-conditioned Day Stay', 'Air-conditioned room', 1, 2, 799, 'per 12-hour stay', 12, 'An air-conditioned room for two with a 12-hour daytime stay from 8 AM to 8 PM.', 'https://images.unsplash.com/photo-1600210492486-724fe5c67fb0?auto=format&fit=crop&w=1000&q=80', ['Free breakfast', 'Free Wi-Fi', 'Netflix', 'Private CR', 'Towel', 'Hygiene kit']],
            ['Whole House — Panal (10 Guests)', 'Whole house', 3, 10, 4500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch, ideal for families and groups of up to 10.', 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1000&q=80', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
            ['Whole House — Panal (20 Guests)', 'Whole house', 3, 20, 5500, 'per night', null, 'A three-bedroom whole-house stay at the Panal branch for larger groups of up to 20 guests.', 'https://images.unsplash.com/photo-1590490360182-c33d57733427?auto=format&fit=crop&w=1000&q=80', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
        ] as [$name, $type, $beds, $guests, $price, $rateLabel, $rentalHours, $description, $image, $amenities]) {
            Room::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name, 'room_type' => $type, 'beds' => $beds, 'guests' => $guests,
                'price_per_night' => $price, 'rate_label' => $rateLabel, 'rental_hours' => $rentalHours,
            'description' => $description, 'image_url' => $image, 'amenities' => $amenities, 'is_active' => true,
            ]);
        }
    }
}
