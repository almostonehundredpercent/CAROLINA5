<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HostedSetupSeeder extends Seeder
{
    /**
     * Add the public room catalogue and one administrator chosen in deployment settings.
     */
    public function run(): void
    {
        if (\App\Models\Room::query()->doesntExist()) $this->call(RoomSeeder::class);

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('No hosted admin was created because ADMIN_EMAIL and ADMIN_PASSWORD are not set.');

            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Carolina Administrator'),
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Optional, deployment-only staff account for acceptance testing.
        // Its email and password live exclusively in the host environment;
        // leaving either setting blank means no account is created.
        $testEmail = env('TEST_STAFF_EMAIL');
        $testPassword = env('TEST_STAFF_PASSWORD');
        $testRole = env('TEST_STAFF_ROLE', 'front_desk');
        if ($testEmail && $testPassword && in_array($testRole, ['front_desk', 'housekeeping', 'viewer'], true)) {
            User::updateOrCreate(
                ['email' => $testEmail],
                [
                    'name' => env('TEST_STAFF_NAME', 'Carolina Test Staff'),
                    'password' => Hash::make($testPassword),
                    'is_admin' => false,
                    'staff_role' => $testRole,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
