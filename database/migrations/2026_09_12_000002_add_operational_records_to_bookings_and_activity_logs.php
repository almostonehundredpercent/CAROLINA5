<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('pending')->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
            $table->timestamp('cancelled_at')->nullable()->after('checked_out_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_by');
            $table->index(['payment_status', 'paid_at']);
            $table->index(['status', 'check_in_at', 'check_out_at']);
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->after('booking_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            $table->json('before_values')->nullable()->after('description');
            $table->json('after_values')->nullable()->after('before_values');
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id', 'before_values', 'after_values']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['payment_status', 'paid_at']);
            $table->dropIndex(['status', 'check_in_at', 'check_out_at']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn(['payment_status', 'paid_at', 'cancelled_at', 'cancelled_by', 'cancellation_reason']);
        });
    }
};
