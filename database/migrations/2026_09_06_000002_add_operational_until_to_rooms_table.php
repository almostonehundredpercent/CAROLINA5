<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::table('rooms', fn (Blueprint $table) => $table->timestamp('operational_until')->nullable()->after('operational_status')); }
    public function down(): void { Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('operational_until')); }
};
