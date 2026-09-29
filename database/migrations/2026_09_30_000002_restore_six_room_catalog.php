<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('rooms')
                ->whereIn('slug', array_map(fn (int $number): string => 'carolina-room-'.$number, range(7, 18)))
                ->update(['is_active' => false, 'updated_at' => now()]);

            foreach ([
                'fan-room-solo' => 'Fan Room — Solo',
                'air-conditioned-room-couple' => 'Air-conditioned Room — Couple',
                'fan-room-couple' => 'Fan Room — Couple',
                'air-conditioned-day-stay' => 'Air-conditioned Day Stay',
                'whole-house-panal-10-guests' => 'Whole House — Panal (10 Guests)',
                'whole-house-panal-20-guests' => 'Whole House — Panal (20 Guests)',
            ] as $slug => $name) {
                DB::table('rooms')
                    ->where('slug', $slug)
                    ->where('name', 'like', 'Room %')
                    ->update(['name' => $name, 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        // Intentionally retain the archive state to protect bookings and room history.
    }
};
