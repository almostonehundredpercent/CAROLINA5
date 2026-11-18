<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('check_in_reminder_sent_at')->nullable();
            $table->index(['status', 'check_in_reminder_sent_at'], 'bookings_check_in_reminder_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_check_in_reminder_lookup_index');
            $table->dropColumn('check_in_reminder_sent_at');
        });
    }
};
