<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('notes', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['room_id', 'starts_at', 'ends_at']);
        });

        DB::table('bookings')->whereNull('check_in_at')->update([
            'check_in_at' => DB::raw('check_in'),
            'check_out_at' => DB::raw('check_out'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocks');
    }
};
