<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('arrival_update')->nullable();
            $table->timestamp('arrival_updated_at')->nullable();
            $table->string('readiness')->default('unconfirmed');
            $table->timestamp('ready_estimate')->nullable();
            $table->timestamp('readiness_updated_at')->nullable();
            $table->boolean('bag_drop_available')->default(false);
            $table->text('arrival_instructions')->nullable();
        });
        Schema::create('stay_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stay_notices');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn(['arrival_update', 'arrival_updated_at', 'readiness', 'ready_estimate', 'readiness_updated_at', 'bag_drop_available', 'arrival_instructions']));
    }
};
