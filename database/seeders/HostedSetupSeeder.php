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
        $testAccounts = [
            [env('TEST_STAFF_EMAIL'), env('TEST_STAFF_PASSWORD'), env('TEST_STAFF_ROLE', 'front_desk'), env('TEST_STAFF_NAME', 'Carolina Test Staff')],
            [env('TEST_HOUSEKEEPING_EMAIL'), env('TEST_HOUSEKEEPING_PASSWORD'), 'housekeeping', env('TEST_HOUSEKEEPING_NAME', 'Carolina Test Housekeeping')],
            [env('TEST_VIEWER_EMAIL'), env('TEST_VIEWER_PASSWORD'), 'viewer', env('TEST_VIEWER_NAME', 'Carolina Test Viewer')],
        ];

        foreach ($testAccounts as [$testEmail, $testPassword, $testRole, $testName]) {
            if (! $testEmail || ! $testPassword || ! in_array($testRole, ['front_desk', 'housekeeping', 'viewer'], true)) continue;

            User::updateOrCreate(
                ['email' => $testEmail],
                [
                    'name' => $testName,
                    'password' => Hash::make($testPassword),
                    'is_admin' => false,
                    'staff_role' => $testRole,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
