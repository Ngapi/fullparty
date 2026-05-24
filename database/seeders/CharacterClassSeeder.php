<?php

namespace Database\Seeders;

use App\Models\CharacterClass;
use App\Support\SeedData\ReferenceIconCatalog;
use Illuminate\Database\Seeder;

class CharacterClassSeeder extends Seeder
{
    /**
     * Seed the application's character classes.
     */
    public function run(): void
    {
        foreach (ReferenceIconCatalog::characterClasses() as $characterClass) {
            CharacterClass::updateOrCreate(
                ['shorthand' => $characterClass['shorthand']],
                [
                    'name' => $characterClass['name'],
                    'role' => $characterClass['role'],
                    'icon_url' => $characterClass['icon_url'],
                    'flaticon_url' => $characterClass['flaticon_url'],
                ]
            );
        }
    }
}
