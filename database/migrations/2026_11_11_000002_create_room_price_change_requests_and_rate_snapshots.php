<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_price_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 24)->unique();
            $table->decimal('old_price', 10, 2);
            $table->decimal('proposed_price', 10, 2);
            $table->string('owner_email');
            $table->string('reason', 1000);
            $table->string('status', 24)->default('requested');
            $table->char('approval_token_hash', 64)->nullable();
            $table->timestamp('approval_expires_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('room_rate_at_booking', 10, 2)->nullable()->after('room_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('room_rate_at_booking');
        });

        Schema::dropIfExists('room_price_change_requests');
    }
};
