<?php

use App\Models\FeaturedGroup;
use App\Models\Group;
use App\Models\User;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('returns curated and eligible fallback groups for the featured groups feed', function () {
    Cache::flush();

    $user = User::factory()->create();

    Group::factory()->hidden()->create([
        'name' => 'Hidden Group',
        'description' => 'Hidden groups should not be featured.',
        'banner_image_url' => '/groups/hidden.jpg',
        'created_at' => now()->addMinute(),
    ]);

    $groups = Group::factory()->count(9)->open()->create([
        'banner_image_url' => '/groups/featured.jpg',
    ])->values();

    $groups->each(function (Group $group, int $index): void {
        $group->forceFill([
            'name' => "Featured Group {$index}",
            'banner_image_url' => "/groups/featured-{$index}.jpg",
            'experience_expectation' => 'hardcore',
            'preferred_languages' => ['en', 'de'],
            'tags' => ["Tag {$index}", "Extra {$index}"],
            'created_at' => now()->subMinutes(9 - $index),
            'updated_at' => now()->subMinutes(9 - $index),
        ])->save();
    });

    FeaturedGroup::factory()->create([
        'group_id' => $groups[3]->id,
        'priority' => 100,
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.featured'))
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.name', 'Featured Group 3')
        ->assertJsonPath('data.0.tags.0', 'Tag 3')
        ->assertJsonPath('data.0.experience_expectation', 'hardcore')
        ->assertJsonPath('data.0.experience_badge.value', 'hardcore')
        ->assertJsonPath('data.0.experience_badge.color', '#D77474')
        ->assertJsonPath('data.0.preferred_languages.0', 'en')
        ->assertJsonPath('data.0.tag_badges.0.label', 'Tag 3')
        ->assertJsonPath('data.0.tag_badges.0.color', app(GroupDiscoveryBadgePalette::class)->tagColor('Tag 3'))
        ->assertJsonPath('data.0.stats.member_count', 1)
        ->assertJsonMissing(['name' => 'Hidden Group'])
        ->assertJsonMissing(['name' => 'Featured Group 0']);
});
