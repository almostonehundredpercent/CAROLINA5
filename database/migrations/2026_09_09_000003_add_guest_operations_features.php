<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->json('add_ons')->nullable()->after('special_request');
            $table->timestamp('checked_in_at')->nullable()->after('add_ons');
            $table->timestamp('checked_out_at')->nullable()->after('checked_in_at');
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('description');
            $table->timestamps();
            $table->index(['booking_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['add_ons', 'checked_in_at', 'checked_out_at']);
        });
    }
};
