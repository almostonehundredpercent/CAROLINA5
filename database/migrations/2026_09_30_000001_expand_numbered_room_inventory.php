<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $rooms = DB::table('rooms')
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // A fresh install is populated by RoomSeeder after migrations run.
            if ($rooms->isEmpty()) {
                return;
            }

            if ($rooms->count() !== 6) {
                throw new RuntimeException('Room inventory expansion expected six active source rooms; found '.$rooms->count().'. No room records were changed.');
            }

            $profiles = $rooms->all();
            $now = now();

            foreach ($profiles as $index => $room) {
                DB::table('rooms')->where('id', $room->id)->update([
                    'name' => 'Room '.($index + 1),
                    'updated_at' => $now,
                ]);
            }

            // Reuse each source profile twice, then shuffle the assignment so
            // units 7–18 get an even but random mix of the six existing offers.
            $assignments = array_merge($profiles, $profiles);
            shuffle($assignments);

            $promoCodesByRoom = Schema::hasTable('promo_code_room')
                ? DB::table('promo_code_room')->get()->groupBy('room_id')
                : collect();

            foreach (range(7, 18) as $offset => $number) {
                $profile = $assignments[$offset];
                $slug = 'carolina-room-'.$number;

                if (DB::table('rooms')->where('slug', $slug)->exists()) {
                    throw new RuntimeException('Cannot create '.$slug.' because that room slug already exists. No room records were changed.');
                }

                $roomId = DB::table('rooms')->insertGetId([
                    'name' => 'Room '.$number,
                    'slug' => $slug,
                    'room_type' => $profile->room_type,
                    'description' => $profile->description,
                    'image_url' => $profile->image_url,
                    'beds' => $profile->beds,
                    'guests' => $profile->guests,
                    'price_per_night' => $profile->price_per_night,
                    'rate_label' => $profile->rate_label,
                    'rental_hours' => $profile->rental_hours,
                    'default_check_in_time' => $profile->default_check_in_time,
                    'amenities' => $profile->amenities,
                    'is_active' => true,
                    'operational_status' => 'available',
                    'operational_until' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($promoCodesByRoom->get($profile->id, collect()) as $promoRoom) {
                    DB::table('promo_code_room')->insertOrIgnore([
                        'promo_code_id' => $promoRoom->promo_code_id,
                        'room_id' => $roomId,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach (range(7, 18) as $number) {
                DB::table('rooms')->where('slug', 'carolina-room-'.$number)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
            }

            $originalNames = [
                'fan-room-solo' => 'Fan Room — Solo',
                'air-conditioned-room-couple' => 'Air-conditioned Room — Couple',
                'fan-room-couple' => 'Fan Room — Couple',
                'air-conditioned-day-stay' => 'Air-conditioned Day Stay',
                'whole-house-panal-10-guests' => 'Whole House — Panal (10 Guests)',
                'whole-house-panal-20-guests' => 'Whole House — Panal (20 Guests)',
            ];

            foreach ($originalNames as $slug => $name) {
                DB::table('rooms')->where('slug', $slug)->where('name', 'like', 'Room %')->update([
                    'name' => $name,
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
