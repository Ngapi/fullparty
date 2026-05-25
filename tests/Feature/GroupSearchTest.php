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
        Character::factory()->primary()->create([
            'user_id' => $group->owner_id,
            'name' => "Character {$index}",
            'avatar_url' => "https://example.com/character-avatar-{$index}.png",
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
        ->assertJsonPath('data.0.owner.name', 'Character 6')
        ->assertJsonPath('data.0.owner.avatar_url', 'https://example.com/character-avatar-6.png')
        ->assertJsonPath('data.0.badge_meta.recruiting_status.color', '#4C7DFF')
        ->assertJsonPath('data.0.badge_meta.tags.0.color', app(GroupDiscoveryBadgePalette::class)->tagColor('Tag 6'))
        ->assertJsonMissing(['name' => 'Hidden Group']);
});

it('uses the owner primary character in the initial discovery page payload', function () {
    $user = User::factory()->create();
    $group = Group::factory()->public()->create([
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

it('shares the sidebar group quick links as my, joined, and follower-only buckets', function () {
    $user = User::factory()->create();

    $ownedGroup = Group::factory()->public()->create([
        'owner_id' => $user->id,
        'name' => 'Owned Sidebar Group',
        'slug' => 'ownedsidebar',
    ]);

    $adminGroup = Group::factory()->public()->create([
        'name' => 'Admin Sidebar Group',
        'slug' => 'adminsidebar',
    ]);
    $adminGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_ADMIN,
        'joined_at' => now(),
    ]);

    $memberGroup = Group::factory()->public()->create([
        'name' => 'Member Sidebar Group',
        'slug' => 'membersidebar',
    ]);
    $memberGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $followedOnlyGroup = Group::factory()->public()->create([
        'name' => 'Followed Sidebar Group',
        'slug' => 'followedsidebar',
    ]);
    $followedOnlyGroup->followers()->attach($user->id, [
        'notifications_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/Index')
            ->where('navigation.group_quick_links.my.0.slug', 'adminsidebar')
            ->where('navigation.group_quick_links.my.1.slug', 'ownedsidebar')
            ->where('navigation.group_quick_links.joined.0.slug', 'membersidebar')
            ->where('navigation.group_quick_links.followed.0.slug', 'followedsidebar')
        );
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
