<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setRoomTwoMainPhoto('/images/rooms/room-2-angle-3.jpg');
    }

    public function down(): void
    {
        $this->setRoomTwoMainPhoto('/images/rooms/room-2-angle-1.jpg');
    }

    private function setRoomTwoMainPhoto(string $image): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        DB::table('rooms')->where('slug', 'air-conditioned-room-couple')->update([
            'image_url' => $image,
            'updated_at' => now(),
        ]);
    }
};
