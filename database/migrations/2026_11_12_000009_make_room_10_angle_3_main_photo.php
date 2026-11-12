<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoomTenMainPhoto('/images/rooms/room-10-angle-3.jpg');
    }

    public function down(): void
    {
        $this->setRoomTenMainPhoto('/images/rooms/room-10-angle-1.jpg');
    }

    private function setRoomTenMainPhoto(string $image): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'carolina-room-10')->update([
            'image_url' => $image,
            'updated_at' => now(),
        ]);
    }
};
