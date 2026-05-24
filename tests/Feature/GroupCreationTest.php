<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Support\Input\TextInputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('stores the selected group type when creating a group', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'name' => 'Static Full Party',
            'description' => 'A fixed roster for weekly clears.',
            'datacenter' => 'Light',
            'is_public' => false,
            'is_visible' => true,
            'slug' => 'staticfp',
            'group_type' => Group::TYPE_STATIC,
        ])
        ->assertRedirect(route('groups.show', 'staticfp'));

    $group = Group::query()->where('slug', 'staticfp')->firstOrFail();

    expect($group->group_type)->toBe(Group::TYPE_STATIC)
        ->and($group->memberships()
            ->where('user_id', $user->id)
            ->where('role', GroupMembership::ROLE_OWNER)
            ->exists())->toBeTrue();
});

it('rejects unknown group types when creating a group', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('groups.index'))
        ->post(route('groups.store'), [
            'name' => 'Odd Group',
            'datacenter' => 'Light',
            'is_public' => true,
            'is_visible' => true,
            'slug' => 'oddgroup',
            'group_type' => 'raid-team',
        ])
        ->assertRedirect(route('groups.index'))
        ->assertSessionHasErrors('group_type');

    expect(Group::query()->where('slug', 'oddgroup')->exists())->toBeFalse();
});

it('rejects unsupported profile picture formats when creating a group', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('groups.index'))
        ->post(route('groups.store'), [
            'name' => 'Gif Group',
            'datacenter' => 'Light',
            'is_public' => false,
            'is_visible' => true,
            'slug' => 'gifgroup',
            'group_type' => Group::TYPE_COMMUNITY,
            'profile_picture' => UploadedFile::fake()->create('animated.gif', 64, 'image/gif'),
        ])
        ->assertRedirect(route('groups.index'))
        ->assertSessionHasErrors('profile_picture');

    expect(Group::query()->where('slug', 'gifgroup')->exists())->toBeFalse();
});

it('sanitizes group name and description when creating a group', function () {
    $user = User::factory()->create();
    $sanitizer = app(TextInputSanitizer::class);

    $rawName = "E\u{0301}quipe\u{00A0}\u{200B}Raid";
    $rawDescription = "Line\u{00A0}one\u{200B}\r\nSecond\u{202E} line\t";

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'name' => $rawName,
            'description' => $rawDescription,
            'datacenter' => 'Light',
            'is_public' => false,
            'is_visible' => true,
            'slug' => 'langraid',
            'group_type' => Group::TYPE_COMMUNITY,
        ])
        ->assertRedirect(route('groups.show', 'langraid'));

    $group = Group::query()->where('slug', 'langraid')->firstOrFail();

    expect($group->name)->toBe($sanitizer->sanitizeSingleLine($rawName))
        ->and($group->description)->toBe($sanitizer->sanitizeMultiline($rawDescription));
});

it('stores discovery metadata and exposes inferred region on the group profile', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('groups.store'), [
            'name' => 'Chaos Prog',
            'description' => 'Late night progression group.',
            'datacenter' => 'Light',
            'is_public' => true,
            'is_visible' => true,
            'slug' => 'chaospro',
            'group_type' => Group::TYPE_COMMUNITY,
            'banner_image' => UploadedFile::fake()->image('banner.png', 1500, 500),
            'recruiting_status' => 'open',
            'primary_focuses' => ['progression', 'mount_farming'],
            'experience_expectation' => 'mixed',
            'voice_expectation' => 'preferred',
            'preferred_languages' => ['en', 'ja'],
            'tags' => ['Late Night', 'late night', 'Blind Prog'],
            'active_timezone' => 'Europe/London',
            'active_days' => ['fri', 'sat'],
            'active_start_time' => '19:00',
            'active_end_time' => '22:30',
        ])
        ->assertRedirect(route('groups.show', 'chaospro'));

    $group = Group::query()->where('slug', 'chaospro')->firstOrFail();

    expect($group->banner_image_url)->not->toBeNull()
        ->and($group->banner_image_url)->toContain('/storage/groups/')
        ->and($group->recruiting_status)->toBe('open')
        ->and($group->primary_focuses)->toBe(['progression', 'mount_farming'])
        ->and($group->experience_expectation)->toBe('mixed')
        ->and($group->voice_expectation)->toBe('preferred')
        ->and($group->preferred_languages)->toBe(['en', 'ja'])
        ->and($group->tags)->toBe(['Late Night', 'Blind Prog'])
        ->and($group->active_timezone)->toBe('Europe/London')
        ->and($group->active_days)->toBe(['fri', 'sat'])
        ->and($group->active_start_time)->toBe('19:00')
        ->and($group->active_end_time)->toBe('22:30')
        ->and($group->inferredRegion())->toBe('EU');

    $this->actingAs($user)
        ->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.banner_image_url', $group->banner_image_url)
            ->where('group.region', 'EU')
            ->where('group.recruiting_status', 'open')
            ->where('group.primary_focuses', ['progression', 'mount_farming'])
            ->where('group.experience_expectation', 'mixed')
            ->where('group.voice_expectation', 'preferred')
            ->where('group.preferred_languages', ['en', 'ja'])
            ->where('group.tags', ['Late Night', 'Blind Prog'])
            ->where('group.active_timezone', 'Europe/London')
            ->where('group.active_days', ['fri', 'sat'])
            ->where('group.active_start_time', '19:00')
            ->where('group.active_end_time', '22:30')
        );
});

it('requires a timezone and complete time pair when active schedule metadata is provided', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('groups.index'))
        ->post(route('groups.store'), [
            'name' => 'Schedule Group',
            'datacenter' => 'Light',
            'is_public' => true,
            'is_visible' => true,
            'slug' => 'schedgrp',
            'group_type' => Group::TYPE_COMMUNITY,
            'active_days' => ['fri'],
            'active_start_time' => '19:00',
        ])
        ->assertRedirect(route('groups.index'))
        ->assertSessionHasErrors(['active_end_time', 'active_timezone']);
});
