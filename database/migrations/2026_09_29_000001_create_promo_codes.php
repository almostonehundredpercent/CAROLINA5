<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'fixed_total']);
            $table->decimal('discount_value', 10, 2);
            $table->unsignedInteger('minimum_hours')->nullable();
            $table->unsignedInteger('maximum_hours')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_code_room', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->primary(['promo_code_id', 'room_id']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('promo_code_id')->nullable()->after('room_id')->constrained()->nullOnDelete();
            $table->string('promo_code', 40)->nullable()->after('total_amount');
            $table->decimal('original_amount', 10, 2)->nullable()->after('promo_code');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('original_amount');
        });

        $roomIds = DB::table('rooms')->where(function ($query) {
            $query->whereIn('slug', ['fan-room-solo', 'fan-room-couple'])
                ->orWhereIn('name', ['Fan Room — Solo', 'Fan Room — Couple']);
        })->pluck('id');

        $promoId = DB::table('promo_codes')->insertGetId([
            'code' => 'MONTH6000',
            'name' => 'One month for ₱6,000',
            'description' => 'A 30-day stay for ₱6,000 on selected fan rooms only.',
            'discount_type' => 'fixed_total',
            'discount_value' => 6000,
            'minimum_hours' => 720,
            'maximum_hours' => 720,
            'is_active' => $roomIds->isNotEmpty(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($roomIds as $roomId) {
            DB::table('promo_code_room')->insert(['promo_code_id' => $promoId, 'room_id' => $roomId]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn(['promo_code', 'original_amount', 'discount_amount']);
        });
        Schema::dropIfExists('promo_code_room');
        Schema::dropIfExists('promo_codes');
    }
};
