<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_notes', function (Blueprint $table) {
            $table->id();
            $table->string('guest_key', 320)->index();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->timestamps();
        });
        Schema::create('guest_restrictions', function (Blueprint $table) {
            $table->id();
            $table->string('guest_key', 320)->unique();
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_restrictions');
        Schema::dropIfExists('guest_notes');
    }
};
