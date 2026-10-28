<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_support_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('low_occupancy_threshold')->default(40);
            $table->timestamps();
        });

        DB::table('decision_support_settings')->insert([
            'id' => 1,
            'low_occupancy_threshold' => 40,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('decision_support_seasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_support_seasons');
        Schema::dropIfExists('decision_support_settings');
    }
};
