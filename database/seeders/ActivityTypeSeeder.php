<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed all production-safe activity type reference data.
     */
    public function run(): void
    {
        $this->call([
            LargeContentActivityTypeSeeder::class,
            SavageActivityTypeSeeder::class,
            UltimateActivityTypeSeeder::class,
        ]);
    }
}
