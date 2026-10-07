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
            $baseSlugs = [
                'fan-room-solo',
                'air-conditioned-room-couple',
                'fan-room-couple',
                'air-conditioned-day-stay',
                'whole-house-panal-10-guests',
                'whole-house-panal-20-guests',
            ];
            $profiles = DB::table('rooms')->whereIn('slug', $baseSlugs)->orderBy('id')->lockForUpdate()->get();

            // Skip only a truly empty room table; a partial inventory is an error.
            if ($profiles->isEmpty() && ! DB::table('rooms')->exists()) {
                return;
            }

            if ($profiles->count() !== 6) {
                throw new RuntimeException('Cannot number the room inventory: expected all six original room profiles. No room records were changed.');
            }

            foreach ($profiles as $index => $room) {
                DB::table('rooms')->where('id', $room->id)->update([
                    'name' => 'Room '.($index + 1),
                    'updated_at' => now(),
                ]);
            }

            $profileAssignments = array_merge($profiles->all(), $profiles->all());
            shuffle($profileAssignments);
            $promoCodesByRoom = Schema::hasTable('promo_code_room')
                ? DB::table('promo_code_room')->get()->groupBy('room_id')
                : collect();

            foreach (range(7, 18) as $offset => $number) {
                $slug = 'carolina-room-'.$number;
                $existing = DB::table('rooms')->where('slug', $slug)->lockForUpdate()->first();

                if ($existing) {
                    // Reactivate the archived unit without resetting its profile,
                    // operating state, promo links, or booking history.
                    DB::table('rooms')->where('id', $existing->id)->update([
                        'name' => 'Room '.$number,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);

                    continue;
                }

                $profile = $profileAssignments[$offset];
                $attributes = (array) $profile;
                unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
                $attributes['name'] = 'Room '.$number;
                $attributes['slug'] = $slug;
                $attributes['is_active'] = true;
                if (array_key_exists('operational_status', $attributes)) {
                    $attributes['operational_status'] = 'available';
                }
                if (array_key_exists('operational_until', $attributes)) {
                    $attributes['operational_until'] = null;
                }
                $attributes['created_at'] = now();
                $attributes['updated_at'] = now();
                $roomId = DB::table('rooms')->insertGetId($attributes);

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

            foreach ([
                'fan-room-solo' => 'Fan Room — Solo',
                'air-conditioned-room-couple' => 'Air-conditioned Room — Couple',
                'fan-room-couple' => 'Fan Room — Couple',
                'air-conditioned-day-stay' => 'Air-conditioned Day Stay',
                'whole-house-panal-10-guests' => 'Whole House — Panal (10 Guests)',
                'whole-house-panal-20-guests' => 'Whole House — Panal (20 Guests)',
            ] as $slug => $name) {
                DB::table('rooms')->where('slug', $slug)->where('name', 'like', 'Room %')->update([
                    'name' => $name,
                    'updated_at' => now(),
                ]);
            }
        });
    }
};
