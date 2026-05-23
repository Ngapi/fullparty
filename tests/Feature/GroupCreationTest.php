<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Support\Input\TextInputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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
