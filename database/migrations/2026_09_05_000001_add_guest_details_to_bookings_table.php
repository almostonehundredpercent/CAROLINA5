<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_email')->nullable()->after('guest_name');
            $table->string('guest_phone', 20)->nullable()->after('guest_email');
            $table->string('billing_street')->nullable()->after('guest_phone');
            $table->string('billing_city')->nullable()->after('billing_street');
            $table->string('billing_province')->nullable()->after('billing_city');
            $table->string('billing_postal_code', 12)->nullable()->after('billing_province');
            $table->timestamp('billing_verified_at')->nullable()->after('billing_postal_code');
            $table->index('guest_email');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['guest_email']);
            $table->dropColumn(['guest_name', 'guest_email', 'guest_phone', 'billing_street', 'billing_city', 'billing_province', 'billing_postal_code', 'billing_verified_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
