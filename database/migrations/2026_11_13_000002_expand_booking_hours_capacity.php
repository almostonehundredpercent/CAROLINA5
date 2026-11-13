<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 720 is the one-calendar-month package marker; the old tiny integer
        // column could not store it on MySQL/MariaDB.
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedSmallInteger('hours')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->where('hours', '>', 255)->exists()) {
            throw new RuntimeException('Cannot shrink bookings.hours while bookings have values above 255.');
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedTinyInteger('hours')->nullable()->change();
        });
    }
};
