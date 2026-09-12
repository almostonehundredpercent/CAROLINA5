<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Room data is safe to seed locally. User accounts must be created
        // through registration or the explicitly configured hosted setup
        // seeder; never ship predictable login credentials.
        $this->call([RoomSeeder::class]);
    }
}
