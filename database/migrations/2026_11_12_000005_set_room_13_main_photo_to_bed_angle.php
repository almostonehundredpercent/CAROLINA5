<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoomThirteenMainPhoto('/images/rooms/room-13-angle-2.jpg');
    }

    public function down(): void
    {
        $this->setRoomThirteenMainPhoto('/images/rooms/room-13-angle-1.jpg');
    }

    private function setRoomThirteenMainPhoto(string $image): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'carolina-room-13')->update([
            'image_url' => $image,
            'updated_at' => now(),
        ]);
    }
};
