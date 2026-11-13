<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'carolina-room-9')->update([
            'room_type' => 'Guest room',
            'beds' => 1,
            'guests' => 2,
            'description' => 'A comfortable guest room with one bed for up to two guests, offering a simple place to rest during your stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Room 9 previously inherited randomized profile details, so the previous values are not reliable.
    }
};
