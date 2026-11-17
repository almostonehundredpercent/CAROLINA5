<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cancellation_request_status', 20)->nullable()->after('cancellation_reason');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_request_status');
            $table->timestamp('cancellation_reviewed_at')->nullable()->after('cancellation_requested_at');
            $table->foreignId('cancellation_reviewed_by')->nullable()->after('cancellation_reviewed_at')->constrained('users')->nullOnDelete();
            $table->index(['cancellation_request_status', 'cancellation_requested_at'], 'bookings_cancellation_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_cancellation_queue_idx');
            $table->dropForeign(['cancellation_reviewed_by']);
            $table->dropColumn([
                'cancellation_request_status',
                'cancellation_requested_at',
                'cancellation_reviewed_at',
                'cancellation_reviewed_by',
            ]);
        });
    }
};
