<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('room_type');
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->unsignedTinyInteger('beds')->default(1);
            $table->unsignedTinyInteger('guests')->default(2);
            $table->decimal('price_per_night', 10, 2);
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
