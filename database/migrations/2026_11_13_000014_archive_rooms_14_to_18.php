<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function roomSlugs(): array
    {
        return array_map(fn (int $number) => 'carolina-room-'.$number, range(14, 18));
    }

    public function up(): void
    {
        DB::table('rooms')->whereIn('slug', $this->roomSlugs())->update([
            'is_active' => false,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->whereIn('slug', $this->roomSlugs())->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};
