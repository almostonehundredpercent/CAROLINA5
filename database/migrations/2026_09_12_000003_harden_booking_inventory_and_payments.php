<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->time('default_check_in_time')->nullable()->after('rental_hours');
        });
        DB::table('rooms')->where('slug', 'fan-room-solo')->update(['default_check_in_time' => '12:00:00']);
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('submission_token')->nullable()->unique()->after('reference');
        });
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('ends_at');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 30);
            $table->string('status', 30)->default('pending');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
            $table->index(['status', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['completed_at', 'completed_by']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['submission_token']);
            $table->dropColumn('submission_token');
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('default_check_in_time');
        });
    }
};
