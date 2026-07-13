<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a super admin user
        User::firstOrCreate(
            ['email' => 'admin@carolina.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123456'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create a regular user for testing
        User::firstOrCreate(
            ['email' => 'user@carolina.local'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('user123456'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        // Create additional admin for testing
        User::firstOrCreate(
            ['email' => 'manager@carolina.local'],
            [
                'name' => 'Admin Manager',
                'password' => Hash::make('manager123456'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
