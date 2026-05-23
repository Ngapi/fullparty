<?php

use App\Models\Group;
use App\Models\User;
use App\Support\Input\TextInputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

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
