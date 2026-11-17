<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('early_checkout_status', 20)->nullable()->after('checked_out_at');
            $table->timestamp('early_checkout_requested_at')->nullable()->after('early_checkout_status');
            $table->timestamp('early_checkout_reviewed_at')->nullable()->after('early_checkout_requested_at');
            $table->foreignId('early_checkout_reviewed_by')->nullable()->after('early_checkout_reviewed_at')->constrained('users')->nullOnDelete();
            $table->index(['early_checkout_status', 'early_checkout_requested_at'], 'bookings_early_checkout_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_early_checkout_queue_idx');
            $table->dropForeign(['early_checkout_reviewed_by']);
            $table->dropColumn([
                'early_checkout_status',
                'early_checkout_requested_at',
                'early_checkout_reviewed_at',
                'early_checkout_reviewed_by',
            ]);
        });
    }
};
