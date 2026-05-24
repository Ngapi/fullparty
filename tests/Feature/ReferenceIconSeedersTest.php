<?php

use App\Models\CharacterClass;
use App\Models\PhantomJob;
use App\Support\SeedData\ReferenceIconCatalog;
use Database\Seeders\CharacterClassSeeder;
use Database\Seeders\PhantomJobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds character classes with committed local icon urls', function () {
    $this->seed(CharacterClassSeeder::class);

    $bard = CharacterClass::query()->where('shorthand', 'BRD')->sole();
    $bardSeedData = collect(ReferenceIconCatalog::characterClasses())
        ->firstWhere('shorthand', 'BRD');

    expect($bard->role)->toBe('physical ranged dps')
        ->and($bard->icon_url)->toBe($bardSeedData['icon_url'])
        ->and($bard->flaticon_url)->toBe($bardSeedData['flaticon_url']);
});

it('seeds phantom jobs with committed local icon urls', function () {
    $this->seed(PhantomJobSeeder::class);

    $phantomBard = PhantomJob::query()->where('name', 'Phantom Bard')->sole();
    $phantomBardSeedData = collect(ReferenceIconCatalog::phantomJobs())
        ->firstWhere('name', 'Phantom Bard');

    expect($phantomBard->icon_url)->toBe($phantomBardSeedData['icon_url'])
        ->and($phantomBard->black_icon_url)->toBe($phantomBardSeedData['black_icon_url'])
        ->and($phantomBard->transparent_icon_url)->toBe($phantomBardSeedData['transparent_icon_url'])
        ->and($phantomBard->sprite_url)->toBe($phantomBardSeedData['sprite_url']);
});
