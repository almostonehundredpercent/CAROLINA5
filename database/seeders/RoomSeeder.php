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
            ['fan-room-solo', 'Fan room', 1, 1, 450, 'per 22-hour stay', 22, 'A simple private fan room for one guest, with a 22-hour stay beginning at noon.', '/images/rooms/room-1-angle-1.png', ['Common CR', 'AC/Fan']],
            ['air-conditioned-room-couple', 'Air-conditioned room', 1, 2, 450, '', 6, 'A comfortable air-conditioned room with one bed for up to two guests and access to a shared bathroom.', '/images/rooms/room-2-angle-3.jpg', ['AC/Fan', 'Common CR']],
            ['fan-room-couple', 'Fan room', 1, 2, 450, 'per 22-hour stay', 22, 'A practical fan room for up to two guests, with rates starting at ₱450 for a 22-hour stay.', '/images/rooms/room-3-angle-1.jpg', ['AC/Fan', 'With CR', 'Refrigerator', 'TV']],
            ['air-conditioned-day-stay', 'Air-conditioned room', 1, 2, 450, '', 22, 'Best suited to solo travelers, this air-conditioned room has one bed for up to two guests. 22-hour stays start at ₱450.', '/images/rooms/room-4-angle-1.jpg', ['AC/Fan', 'Common CR']],
            ['whole-house-panal-10-guests', 'Whole house', 3, 2, 4500, 'per night', null, 'A whole-house stay at the Panal branch with room for up to two guests.', '/images/rooms/room-5-angle-1.jpg', ['AC/Fan', 'Common CR', 'TV in room']],
            ['whole-house-panal-20-guests', 'Double-deck room', 2, 6, 450, 'starting price', 6, 'A double-deck room for up to six guests, with a starting rate of ₱450 for a 6-hour air-conditioned stay.', '/images/rooms/room-6-angle-1.png', ['Daily cleaning', 'Complete kitchen wares', 'Large refrigerator', 'Free mineral water', 'Towels', 'Parking']],
        ] as $index => [$slug, $type, $beds, $guests, $price, $rateLabel, $rentalHours, $description, $image, $amenities]) {
            $profiles[] = Room::updateOrCreate(['slug' => $slug], [
                'name' => 'Room '.($index + 1), 'room_type' => $type, 'beds' => $beds, 'guests' => max(2, $guests),
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

                if ($number === 13) {
                    $room->room_type = 'Air-conditioned room';
                    $room->beds = 1;
                    $room->guests = 2;
                    $room->price_per_night = 450;
                    $room->rate_label = 'per night';
                    $room->rental_hours = null;
                    $room->description = 'A comfortable stay for solo travelers, with space for up to two guests. Rates start at ₱450 per night.';
                }

                $room->operational_status = 'available';
                $room->operational_until = null;
            }

            if ($number === 10) {
                $room->room_type = 'Air-conditioned room';
                $room->beds = 1;
                $room->guests = 2;
                $room->price_per_night = 450;
                $room->rate_label = 'per night';
                $room->rental_hours = null;
                $room->description = 'A one-bedroom, air-conditioned stay with one bed for up to two guests. Rates start at ₱450 per night.';
                $room->amenities = collect($room->amenities ?? [])
                    ->reject(fn ($amenity) => is_string($amenity) && strtolower(trim($amenity)) === 'complete kitchen wares')
                    ->values()
                    ->all();
            }

            if ($number === 11) {
                $room->room_type = 'Fan room';
                $room->beds = 1;
                $room->guests = 4;
                $room->price_per_night = 450;
                $room->rate_label = '';
                $room->rental_hours = 22;
                $room->description = 'A comfortable fan room with one bed and space for up to four guests. Check-in begins at noon for a 22-hour stay.';
            }

            $room->save();

            if ($isNew) {
                $room->promoCodes()->syncWithoutDetaching($assignments[$offset]->promoCodes()->pluck('promo_codes.id')->all());
            }
        }

        $photos = [
            7 => '/images/rooms/room-7-angle-4.jpg',
            8 => '/images/rooms/room-8-angle-1.png',
            10 => '/images/rooms/room-10-angle-3.jpg',
            11 => '/images/rooms/room-11-angle-3.jpg',
            13 => '/images/rooms/room-13-angle-2.jpg',
        ];
        $descriptionsByRoom = [
            8 => 'Ideal for a solo traveler, this one-bed room offers a simple and comfortable place to unwind after a busy day. Its practical layout provides a restful base during your stay.',
        ];
        $amenitiesByRoom = [
            7 => ['Common CR', 'Air conditioning'],
            10 => ['Common CR', 'Refrigerator', 'Air conditioning'],
            11 => ['Private CR', 'Refrigerator', 'Air conditioning'],
            13 => ['Private CR', 'Air conditioning'],
        ];
        $replacedAmenity = function ($amenity): bool {
            if (! is_string($amenity)) {
                return false;
            }

            $amenity = strtolower(trim($amenity));

            return preg_match('/\b(?:(?:common|private|shared)\s*)?cr\b|\b(?:bathroom|toilet)\b/i', $amenity)
                || preg_match('/\b(?:a\/c|ac|air[-\s]condition(?:ed|er|ing)?)\b/i', $amenity)
                || preg_match('/\b(?:ref|refrigerator|fridge)\b/i', $amenity);
        };

        $roomNumbers = array_unique(array_merge(array_keys($photos), array_keys($descriptionsByRoom), array_keys($amenitiesByRoom)));

        foreach ($roomNumbers as $number) {
            $room = Room::where('slug', 'carolina-room-'.$number)->first();
            if (! $room) {
                continue;
            }

            if (isset($photos[$number])) {
                $room->image_url = $photos[$number];
            }

            if (isset($descriptionsByRoom[$number])) {
                $room->description = $descriptionsByRoom[$number];
            }

            if (isset($amenitiesByRoom[$number])) {
                $room->amenities = collect($room->amenities ?? [])
                    ->reject($replacedAmenity)
                    ->merge($amenitiesByRoom[$number])
                    ->unique()
                    ->values()
                    ->all();
            }

            $room->save();
        }
    }
}
