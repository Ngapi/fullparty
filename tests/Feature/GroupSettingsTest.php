<?php

use App\Models\Group;
use App\Models\User;
use App\Support\Input\TextInputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('rejects unsupported profile picture formats when updating group settings', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
    ]);

    $this->actingAs($owner)
        ->from(route('groups.dashboard.settings', $group))
        ->put(route('groups.dashboard.settings.update', $group), [
            'name' => $group->name,
            'description' => $group->description,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'profile_picture' => UploadedFile::fake()->create('animated.gif', 64, 'image/gif'),
        ])
        ->assertRedirect(route('groups.dashboard.settings', $group))
        ->assertSessionHasErrors('profile_picture');
});

it('sanitizes group name and description when updating group settings', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
    ]);
    $sanitizer = app(TextInputSanitizer::class);

    $rawName = "Gru\u{0308}ppe\u{00A0}\u{200B}Test";
    $rawDescription = "First\u{00A0}line\u{200B}\r\nSecond\u{202E} line\t";

    $this->actingAs($owner)
        ->put(route('groups.dashboard.settings.update', $group), [
            'name' => $rawName,
            'description' => $rawDescription,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
        ])
        ->assertRedirect();

    $group->refresh();

    expect($group->name)->toBe($sanitizer->sanitizeSingleLine($rawName))
        ->and($group->description)->toBe($sanitizer->sanitizeMultiline($rawDescription));
});

it('stores discovery metadata when updating group settings', function () {
    Storage::fake('public');

    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
        'datacenter' => 'Aether',
    ]);

    $this->actingAs($owner)
        ->put(route('groups.dashboard.settings.update', $group), [
            'name' => $group->name,
            'description' => $group->description,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => 'Chaos',
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'banner_image' => UploadedFile::fake()->image('banner.png', 1500, 500),
            'recruiting_status' => 'selective',
            'primary_focuses' => ['social_community', 'maps'],
            'experience_expectation' => 'beginner_friendly',
            'voice_expectation' => 'optional',
            'preferred_languages' => ['de', 'fr'],
            'tags' => ['Weekend', 'weekend', 'Maps'],
            'active_timezone' => 'Europe/Berlin',
            'active_days' => ['sat', 'sun'],
            'active_start_time' => '18:00',
            'active_end_time' => '23:00',
        ])
        ->assertRedirect();

    $group->refresh();

    expect($group->banner_image_url)->not->toBeNull()
        ->and($group->banner_image_url)->toContain('/storage/groups/')
        ->and($group->datacenter)->toBe('Chaos')
        ->and($group->inferredRegion())->toBe('EU')
        ->and($group->recruiting_status)->toBe('selective')
        ->and($group->primary_focuses)->toBe(['social_community', 'maps'])
        ->and($group->experience_expectation)->toBe('beginner_friendly')
        ->and($group->voice_expectation)->toBe('optional')
        ->and($group->preferred_languages)->toBe(['de', 'fr'])
        ->and($group->tags)->toBe(['Weekend', 'Maps'])
        ->and($group->active_timezone)->toBe('Europe/Berlin')
        ->and($group->active_days)->toBe(['sat', 'sun'])
        ->and($group->active_start_time)->toBe('18:00')
        ->and($group->active_end_time)->toBe('23:00');

    $this->actingAs($owner)
        ->get(route('groups.dashboard.settings', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.region', 'EU')
            ->where('group.banner_image_url', $group->banner_image_url)
            ->where('group.recruiting_status', 'selective')
            ->where('group.primary_focuses', ['social_community', 'maps'])
            ->where('group.experience_expectation', 'beginner_friendly')
            ->where('group.voice_expectation', 'optional')
            ->where('group.preferred_languages', ['de', 'fr'])
            ->where('group.tags', ['Weekend', 'Maps'])
            ->where('group.active_timezone', 'Europe/Berlin')
            ->where('group.active_days', ['sat', 'sun'])
            ->where('group.active_start_time', '18:00')
            ->where('group.active_end_time', '23:00')
        );
});

it('preserves existing discovery metadata when omitted from a settings update', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
        'recruiting_status' => 'open',
        'primary_focuses' => ['progression'],
        'experience_expectation' => 'mixed',
        'voice_expectation' => 'preferred',
        'preferred_languages' => ['en', 'ja'],
        'tags' => ['Late Night'],
        'active_timezone' => 'Europe/London',
        'active_days' => ['fri'],
        'active_start_time' => '19:00',
        'active_end_time' => '22:00',
    ]);

    $this->actingAs($owner)
        ->put(route('groups.dashboard.settings.update', $group), [
            'name' => 'Updated Name',
            'description' => $group->description,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
        ])
        ->assertRedirect();

    $group->refresh();

    expect($group->name)->toBe('Updated Name')
        ->and($group->recruiting_status)->toBe('open')
        ->and($group->primary_focuses)->toBe(['progression'])
        ->and($group->experience_expectation)->toBe('mixed')
        ->and($group->voice_expectation)->toBe('preferred')
        ->and($group->preferred_languages)->toBe(['en', 'ja'])
        ->and($group->tags)->toBe(['Late Night'])
        ->and($group->active_timezone)->toBe('Europe/London')
        ->and($group->active_days)->toBe(['fri'])
        ->and($group->active_start_time)->toBe('19:00')
        ->and($group->active_end_time)->toBe('22:00');
});
