<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_support_settings', function (Blueprint $table) {
            $table->boolean('unpaid_hold_reminders_enabled')->default(false);
            $table->timestamp('unpaid_hold_reminder_dismissed_at')->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('payment_reminder_sent_at')->nullable();
            $table->index('payment_reminder_sent_at', 'bookings_payment_reminder_sent_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_payment_reminder_sent_at_index');
            $table->dropColumn('payment_reminder_sent_at');
        });

        Schema::table('decision_support_settings', function (Blueprint $table) {
            $table->dropColumn(['unpaid_hold_reminders_enabled', 'unpaid_hold_reminder_dismissed_at']);
        });
    }
};
