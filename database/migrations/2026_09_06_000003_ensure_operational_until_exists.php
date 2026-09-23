<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('rooms', 'operational_until')) {
            Schema::table('rooms', fn (Blueprint $table) => $table->timestamp('operational_until')->nullable());
        }
    }

    public function down(): void {}
};
