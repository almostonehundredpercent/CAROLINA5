<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('is_admin', true)->update(['staff_role' => 'organization_admin']);
        DB::table('users')->whereIn('staff_role', ['admin', 'super_admin'])->update(['staff_role' => 'organization_admin']);
    }

    public function down(): void
    {
        DB::table('users')->where('staff_role', 'organization_admin')->update(['staff_role' => 'admin']);
    }
};
