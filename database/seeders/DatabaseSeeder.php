<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Note: user accounts are created only via the `user:create` CLI command,
     * so no users are seeded here.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
        ]);
    }
}
