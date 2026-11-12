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

        DB::table('rooms')->where('slug', 'carolina-room-13')->update([
            'description' => 'A comfortable stay for solo travelers, with space for up to two guests. Rates start at ₱450 per night.',
            'guests' => 2,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Room 13 may have been edited after this content update, so preserve
        // its current details instead of restoring an unknown prior description.
    }
};
