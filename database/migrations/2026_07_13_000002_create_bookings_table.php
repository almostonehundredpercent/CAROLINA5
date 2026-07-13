<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::create('bookings', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('room_id')->constrained()->cascadeOnDelete(); $table->string('reference')->unique(); $table->date('check_in'); $table->date('check_out'); $table->unsignedTinyInteger('guests'); $table->unsignedSmallInteger('nights'); $table->decimal('total_amount', 10, 2); $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending'); $table->string('payment_method'); $table->text('special_request')->nullable(); $table->timestamps(); $table->index(['room_id', 'check_in', 'check_out']); }); } public function down(): void { Schema::dropIfExists('bookings'); } };
