<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->after('total_amount');
            $table->text('staff_notes')->nullable()->after('special_request');
            $table->index(['status', 'hold_expires_at']);
            $table->index(['room_id', 'status', 'check_in_at', 'check_out_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['status', 'hold_expires_at']);
            $table->dropIndex(['room_id', 'status', 'check_in_at', 'check_out_at']);
            $table->dropColumn(['hold_expires_at', 'staff_notes']);
        });
    }
};
