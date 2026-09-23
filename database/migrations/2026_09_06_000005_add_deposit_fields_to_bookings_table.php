<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->string('deposit_status', 30)->default('not_required');
            $table->string('payment_reference', 100)->nullable();
            $table->longText('payment_proof_data')->nullable();
            $table->string('payment_proof_mime', 100)->nullable();
            $table->timestamp('deposit_submitted_at')->nullable();
            $table->timestamp('deposit_due_at')->nullable();
            $table->timestamp('deposit_verified_at')->nullable();
            $table->unsignedBigInteger('deposit_verified_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_status', 'payment_reference', 'payment_proof_data', 'payment_proof_mime', 'deposit_submitted_at', 'deposit_due_at', 'deposit_verified_at', 'deposit_verified_by']);
        });
    }
};
