<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('bookings', function (Blueprint $table) { $table->string('booking_type', 20)->default('dates'); $table->unsignedTinyInteger('hours')->nullable(); $table->timestamp('check_in_at')->nullable(); $table->timestamp('check_out_at')->nullable(); }); } public function down(): void { Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['booking_type','hours','check_in_at','check_out_at'])); } };
