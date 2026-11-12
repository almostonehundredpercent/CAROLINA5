<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoomElevenMainPhoto('/images/rooms/room-11-angle-3.jpg');
    }

    public function down(): void
    {
        $this->setRoomElevenMainPhoto('/images/rooms/room-11-angle-1.jpg');
    }

    private function setRoomElevenMainPhoto(string $image): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'carolina-room-11')->update([
            'image_url' => $image,
            'updated_at' => now(),
        ]);
    }
};
