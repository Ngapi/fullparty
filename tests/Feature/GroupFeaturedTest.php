<?php

use App\Models\Group;
use App\Models\User;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the latest eight visible groups for the featured groups feed', function () {
    $user = User::factory()->create();

    Group::factory()->hidden()->create([
        'name' => 'Hidden Group',
        'created_at' => now()->addMinute(),
    ]);

    $groups = Group::factory()->count(9)->open()->create()->values();

    $groups->each(function (Group $group, int $index): void {
        $group->forceFill([
            'name' => "Featured Group {$index}",
            'experience_expectation' => 'hardcore',
            'preferred_languages' => ['en', 'de'],
            'tags' => ["Tag {$index}", "Extra {$index}"],
            'created_at' => now()->subMinutes(9 - $index),
        ])->save();
    });

    $this->actingAs($user)
        ->getJson(route('groups.featured'))
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.name', 'Featured Group 8')
        ->assertJsonPath('data.0.tags.0', 'Tag 8')
        ->assertJsonPath('data.0.experience_expectation', 'hardcore')
        ->assertJsonPath('data.0.experience_badge.value', 'hardcore')
        ->assertJsonPath('data.0.experience_badge.color', '#D77474')
        ->assertJsonPath('data.0.preferred_languages.0', 'en')
        ->assertJsonPath('data.0.tag_badges.0.label', 'Tag 8')
        ->assertJsonPath('data.0.tag_badges.0.color', app(GroupDiscoveryBadgePalette::class)->tagColor('Tag 8'))
        ->assertJsonPath('data.0.stats.member_count', 1)
        ->assertJsonMissing(['name' => 'Hidden Group'])
        ->assertJsonMissing(['name' => 'Featured Group 0']);
});
