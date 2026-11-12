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

        DB::table('rooms')->where('slug', 'carolina-room-11')->update([
            'description' => 'A comfortable fan room with one bed and space for up to four guests. Check-in begins at noon for a 22-hour stay.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep the current room copy when rolling back application code.
    }
};
