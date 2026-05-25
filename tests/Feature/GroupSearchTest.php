<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function attachAdditionalMembers(Group $group, int $count): void
{
    User::factory()->count($count)->create()->each(function (User $member) use ($group): void {
        GroupMembership::query()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
    });
}

it('returns the initial discovery results when the query is empty', function () {
    $user = User::factory()->create();

    Group::factory()->public()->withMember($user)->create([
        'name' => 'Joined Group',
        'slug' => 'joinedgrp',
        'created_at' => now()->subDay(),
    ]);

    Group::factory()->hidden()->create([
        'name' => 'Hidden Group',
        'slug' => 'hiddengrp',
    ]);

    $groups = Group::factory()->count(7)->public()->create()->values();

    $groups->each(function (Group $group, int $index): void {
        $group->owner->update([
            'name' => "Owner {$index}",
            'avatar_url' => "https://example.com/avatar-{$index}.png",
        ]);

        $group->forceFill([
            'name' => "Search Group {$index}",
            'slug' => "srchgrp{$index}",
            'description' => "Discovery group {$index}",
            'recruiting_status' => 'looking_for_members',
            'primary_focuses' => ['progression'],
            'experience_expectation' => 'mixed',
            'voice_expectation' => 'preferred',
            'preferred_languages' => ['en'],
            'tags' => ["Tag {$index}"],
            'created_at' => now()->subMinutes(7 - $index),
        ])->save();
    });

    $response = $this->actingAs($user)
        ->getJson(route('groups.search'));

    $response
        ->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 6)
        ->assertJsonPath('meta.total', 8)
        ->assertJsonPath('data.0.name', 'Search Group 6')
        ->assertJsonPath('data.0.owner.name', 'Owner 6')
        ->assertJsonPath('data.0.badge_meta.recruiting_status.color', '#4C7DFF')
        ->assertJsonPath('data.0.badge_meta.tags.0.color', app(GroupDiscoveryBadgePalette::class)->tagColor('Tag 6'))
        ->assertJsonMissing(['name' => 'Hidden Group']);
});

it('includes groups the current user already belongs to in discovery results', function () {
    $user = User::factory()->create();

    Group::factory()->public()->withMember($user)->create([
        'name' => 'Joined Group',
        'slug' => 'joinedgrp',
        'group_type' => Group::TYPE_STATIC,
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.search', [
            'group_type' => Group::TYPE_STATIC,
        ]))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Joined Group',
            'slug' => 'joinedgrp',
        ]);
});

it('applies discovery filters to the group search results', function () {
    $user = User::factory()->create();

    $matchingGroup = Group::factory()->public()->create([
        'name' => 'Night Prog',
        'slug' => 'nightpro',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Light',
        'recruiting_status' => 'applications_open',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night', 'Week 1'],
        'active_days' => ['fri'],
    ]);

    attachAdditionalMembers($matchingGroup, 49);

    Group::factory()->public()->create([
        'name' => 'Wrong Region',
        'slug' => 'wrongreg',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Aether',
        'recruiting_status' => 'applications_open',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night'],
        'active_days' => ['fri'],
    ]);

    Group::factory()->public()->create([
        'name' => 'Wrong Focus',
        'slug' => 'wrongfoc',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Light',
        'recruiting_status' => 'applications_open',
        'primary_focuses' => ['maps'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night'],
        'active_days' => ['fri'],
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.search', [
            'group_type' => Group::TYPE_STATIC,
            'experience_expectation' => 'hardcore',
            'region' => 'EU',
            'size' => '50',
            'sort_by' => 'member_count_desc',
            'recruiting_status' => 'applications_open',
            'primary_focuses' => ['progression'],
            'voice_expectation' => 'required',
            'preferred_languages' => ['en'],
            'active_days' => ['fri'],
            'extra_tags' => 'late',
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Night Prog')
        ->assertJsonPath('data.0.region', 'EU')
        ->assertJsonPath('data.0.stats.member_count', 50);
});

it('rejects oversized discovery search input payloads', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('groups.search', [
            'query' => str_repeat('a', 256),
            'extra_tags' => str_repeat('b', 256),
            'preferred_languages' => ['en', 'de', 'fr', 'ja', 'en'],
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'query',
            'extra_tags',
            'preferred_languages',
        ]);
});
