<?php

use App\Models\Character;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

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

    Group::factory()->open()->withMember($user)->create([
        'name' => 'Joined Group',
        'slug' => 'joingrp',
        'created_at' => now()->subDay(),
    ]);

    Group::factory()->hidden()->create([
        'name' => 'Hidden Group',
        'slug' => 'hiddengp',
    ]);

    $groups = Group::factory()->count(7)->open()->create()->values();

    $groups->each(function (Group $group, int $index): void {
        $group->owner->update([
            'name' => "Owner {$index}",
            'avatar_url' => "https://example.com/avatar-{$index}.png",
        ]);
        Character::factory()->primary()->create([
            'user_id' => $group->owner_id,
            'name' => "Character {$index}",
            'avatar_url' => "https://example.com/character-avatar-{$index}.png",
        ]);

        $group->forceFill([
            'name' => "Search Group {$index}",
            'slug' => "srchgrp{$index}",
            'description' => "Discovery group {$index}",
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
        ->assertJsonPath('data.0.owner.name', 'Character 6')
        ->assertJsonPath('data.0.owner.avatar_url', 'https://example.com/character-avatar-6.png')
        ->assertJsonPath('data.0.badge_meta.tags.0.color', app(GroupDiscoveryBadgePalette::class)->tagColor('Tag 6'))
        ->assertJsonMissing(['name' => 'Hidden Group']);
});

it('uses the owner primary character in the initial discovery page payload', function () {
    $user = User::factory()->create();
    $group = Group::factory()->open()->create([
        'name' => 'Initial Discovery Group',
        'slug' => 'initdisc',
    ]);

    $group->owner->update([
        'name' => 'Fallback Owner Name',
        'avatar_url' => 'https://example.com/fallback-owner.png',
    ]);

    Character::factory()->primary()->create([
        'user_id' => $group->owner_id,
        'name' => 'Owner Main Character',
        'avatar_url' => 'https://example.com/owner-main-character.png',
    ]);

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/Index')
            ->where('discoverGroups.data.0.owner.name', 'Owner Main Character')
            ->where('discoverGroups.data.0.owner.avatar_url', 'https://example.com/owner-main-character.png')
        );
});

it('shares the sidebar group quick links as my and joined buckets', function () {
    $user = User::factory()->create();

    $ownedGroup = Group::factory()->open()->create([
        'owner_id' => $user->id,
        'name' => 'Owned Sidebar Group',
        'slug' => 'ownedsb',
    ]);

    $adminGroup = Group::factory()->open()->create([
        'name' => 'Admin Sidebar Group',
        'slug' => 'adminsb',
    ]);
    $adminGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_ADMIN,
        'joined_at' => now(),
    ]);

    $memberGroup = Group::factory()->open()->create([
        'name' => 'Member Sidebar Group',
        'slug' => 'membersb',
    ]);
    $memberGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/Index')
            ->where('navigation.group_quick_links.my.0.slug', 'adminsb')
            ->where('navigation.group_quick_links.my.1.slug', 'ownedsb')
            ->where('navigation.group_quick_links.joined.0.slug', 'membersb')
        );
});

it('includes groups the current user already belongs to in discovery results', function () {
    $user = User::factory()->create();

    Group::factory()->open()->withMember($user)->create([
        'name' => 'Joined Group',
        'slug' => 'joingrp',
        'group_type' => Group::TYPE_STATIC,
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.search', [
            'group_type' => Group::TYPE_STATIC,
        ]))
        ->assertOk()
        ->assertJsonFragment([
            'name' => 'Joined Group',
            'slug' => 'joingrp',
        ]);
});

it('applies discovery filters to the group search results', function () {
    $user = User::factory()->create();

    $matchingGroup = Group::factory()->applicationBased()->create([
        'name' => 'Night Prog',
        'slug' => 'nightpro',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Light',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night', 'Week 1'],
        'active_days' => ['fri'],
    ]);

    attachAdditionalMembers($matchingGroup, 49);

    Group::factory()->applicationBased()->create([
        'name' => 'Wrong Region',
        'slug' => 'wrongreg',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Aether',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night'],
        'active_days' => ['fri'],
    ]);

    Group::factory()->applicationBased()->create([
        'name' => 'Wrong Focus',
        'slug' => 'wrongfoc',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Light',
        'primary_focuses' => ['maps'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night'],
        'active_days' => ['fri'],
    ]);

    Group::factory()->inviteOnly()->create([
        'name' => 'Wrong Method',
        'slug' => 'wrongmet',
        'group_type' => Group::TYPE_STATIC,
        'datacenter' => 'Light',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'hardcore',
        'voice_expectation' => 'required',
        'preferred_languages' => ['en'],
        'tags' => ['Late Night'],
        'active_days' => ['fri'],
    ]);

    $this->actingAs($user)
        ->getJson(route('groups.search', [
            'group_type' => Group::TYPE_STATIC,
            'join_mode' => Group::JOIN_MODE_APPLICATION,
            'experience_expectation' => 'hardcore',
            'region' => 'EU',
            'size' => '50',
            'sort_by' => 'member_count_desc',
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
