<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoomSevenMainPhoto('/images/rooms/room-7-angle-4.jpg');
    }

    public function down(): void
    {
        $this->setRoomSevenMainPhoto('/images/rooms/room-7-angle-1.jpg');
    }

    private function setRoomSevenMainPhoto(string $image): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'carolina-room-7')->update([
            'image_url' => $image,
            'updated_at' => now(),
        ]);
    }
};
