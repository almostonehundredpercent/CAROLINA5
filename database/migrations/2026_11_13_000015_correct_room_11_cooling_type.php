<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'carolina-room-11')->update([
            'room_type' => 'Air-conditioned room',
            'description' => 'A comfortable air-conditioned room with one bed and space for up to four guests. Check-in begins at noon for a 22-hour stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('rooms')->where('slug', 'carolina-room-11')->update([
            'room_type' => 'Fan room',
            'description' => 'A comfortable fan room with one bed and space for up to four guests. Check-in begins at noon for a 22-hour stay.',
            'updated_at' => now(),
        ]);
    }
};
