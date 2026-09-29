<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $promo = DB::table('promo_codes')->where('code', 'CAROLINA')->first();

        if ($promo) {
            DB::table('promo_codes')->where('id', $promo->id)->update([
                'name' => 'Carolina 10% Off',
                'description' => 'Save 10% on any Carolina room and stay length.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'minimum_hours' => null,
                'maximum_hours' => null,
                'starts_at' => null,
                'ends_at' => null,
                'usage_limit' => null,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            $promoId = $promo->id;
        } else {
            $promoId = DB::table('promo_codes')->insertGetId([
                'code' => 'CAROLINA',
                'name' => 'Carolina 10% Off',
                'description' => 'Save 10% on any Carolina room and stay length.',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'minimum_hours' => null,
                'maximum_hours' => null,
                'starts_at' => null,
                'ends_at' => null,
                'usage_limit' => null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('rooms')->pluck('id') as $roomId) {
            DB::table('promo_code_room')->updateOrInsert([
                'promo_code_id' => $promoId,
                'room_id' => $roomId,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('promo_codes')->where('code', 'CAROLINA')->delete();
    }
};
