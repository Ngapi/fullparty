<?php

use App\Models\Group;
use App\Models\User;
use Database\Seeders\GroupSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('seeds groups with populated discovery metadata and banner images', function () {
    Storage::fake('public');

    $this->seed(UserSeeder::class);
    $this->seed(GroupSeeder::class);

    $groups = Group::query()->orderBy('id')->get();
    $forkedTowerGroup = Group::query()->where('slug', 'ftel')->sole();
    $developmentOwner = User::query()->findOrFail($forkedTowerGroup->owner_id);

    expect($groups)->toHaveCount(20)
        ->and($groups->every(fn (Group $group) => filled($group->banner_image_url)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->recruiting_status)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => ($group->primary_focuses ?? []) !== []))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->experience_expectation)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->voice_expectation)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => ($group->preferred_languages ?? []) !== []))->toBeTrue()
        ->and($groups->every(fn (Group $group) => ($group->tags ?? []) !== []))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->active_timezone)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => ($group->active_days ?? []) !== []))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->active_start_time)))->toBeTrue()
        ->and($groups->every(fn (Group $group) => filled($group->active_end_time)))->toBeTrue()
        ->and($forkedTowerGroup->inferredRegion())->toBe('EU')
        ->and($forkedTowerGroup->owner_id)->toBe(1)
        ->and($forkedTowerGroup->recruiting_status)->toBe('applications_open')
        ->and($forkedTowerGroup->primary_focuses)->toBe(['progression', 'clears', 'reclears'])
        ->and($forkedTowerGroup->preferred_languages)->toBe(['en', 'de', 'fr'])
        ->and($developmentOwner->is_admin)->toBeFalse();

    if (function_exists('imagecreatetruecolor')) {
        expect($forkedTowerGroup->banner_image_url)->toContain('/storage/groups/seeded-banners/ftel.png');
        Storage::disk('public')->assertExists('groups/seeded-banners/ftel.png');
    } else {
        expect($forkedTowerGroup->banner_image_url)->toBe('/prereqimages/forked.jpg');
    }
});
