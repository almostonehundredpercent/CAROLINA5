<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->after('ends_at');
        });

        Schema::create('booking_time_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_block_id')->nullable()->constrained('room_blocks')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->unsignedSmallInteger('hours');
            $table->decimal('amount', 10, 2);
            $table->timestamp('previous_start');
            $table->timestamp('previous_end');
            $table->timestamp('proposed_start');
            $table->timestamp('proposed_end');
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_time_adjustments');
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropColumn('hold_expires_at');
        });
    }
};
