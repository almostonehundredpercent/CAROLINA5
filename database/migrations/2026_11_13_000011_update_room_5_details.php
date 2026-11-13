<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('rooms')->where('slug', 'whole-house-panal-10-guests')->update([
            'room_type' => 'Guest room',
            'beds' => 1,
            'guests' => 3,
            'description' => 'A comfortable guest room with one bed for up to three guests. It offers a simple, welcoming space to rest during your stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The room’s previous details may have been edited in the admin panel.
    }
};
