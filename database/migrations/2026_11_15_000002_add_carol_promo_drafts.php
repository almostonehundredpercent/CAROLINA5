<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->string('review_status', 16)->default('approved')->index();
            $table->string('carol_generation_key', 160)->nullable()->unique();
            $table->string('carol_category', 16)->nullable();
            $table->string('carol_notes', 500)->nullable();
            $table->date('stay_start_date')->nullable();
            $table->date('stay_end_date')->nullable();
            $table->string('stay_day_type', 16)->nullable();
        });

        Schema::table('decision_support_seasons', function (Blueprint $table) {
            $table->string('kind', 16)->default('season');
        });
    }

    public function down(): void
    {
        Schema::table('decision_support_seasons', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            $table->dropUnique(['carol_generation_key']);
            $table->dropIndex(['review_status']);
            $table->dropColumn([
                'review_status', 'carol_generation_key', 'carol_category', 'carol_notes',
                'stay_start_date', 'stay_end_date', 'stay_day_type',
            ]);
        });
    }
};
