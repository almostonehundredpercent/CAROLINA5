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
        $this->call(RoomSeeder::class);

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
    }
}
