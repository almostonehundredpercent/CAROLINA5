<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cooling_type', 12)->nullable()->after('booking_type');
        });

        // The new package prices include one or two guests. Keep each room's
        // existing larger capacity, while allowing a second guest in singles.
        DB::table('rooms')->where('guests', '<', 2)->update(['guests' => 2]);
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cooling_type');
        });
        // Do not reduce room capacities on rollback; an administrator may
        // have edited them after the migration ran.
    }
};
