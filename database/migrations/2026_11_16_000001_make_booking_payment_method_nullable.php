<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->change();
        });

        DB::table('bookings')
            ->where('payment_method', 'cash')
            ->where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereNull('paid_at')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('payments')
                    ->whereColumn('payments.booking_id', 'bookings.id')
                    ->where('payments.status', 'paid');
            })
            ->update(['payment_method' => null]);
    }

    public function down(): void
    {
        DB::table('bookings')->whereNull('payment_method')->update(['payment_method' => 'unrecorded']);

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_method')->nullable(false)->change();
        });
    }
};
