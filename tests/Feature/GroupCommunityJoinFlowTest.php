<?php

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('does not expose or allow the public join action for static groups', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_STATIC,
    ]);

    expect($group->invites()->exists())->toBeFalse();

    $this->actingAs($viewer)
        ->from(route('groups.index'))
        ->post(route('groups.join', $group))
        ->assertRedirect(route('groups.index'))
        ->assertSessionHasErrors('error');

    expect($group->memberships()->where('user_id', $viewer->id)->exists())->toBeFalse();
});

it('does not expose invite management for static groups', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_STATIC,
    ]);

    GroupInvite::query()->create([
        'group_id' => $group->id,
        'created_by' => $owner->id,
        'token' => 'staticjoin',
        'is_system' => false,
    ]);

    $this->actingAs($owner)
        ->get(route('groups.dashboard.settings', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.group_type', Group::TYPE_STATIC)
            ->where('group.permissions.can_manage_invites', false)
            ->has('group.invites', 0)
        );

    $this->actingAs($owner)
        ->from(route('groups.dashboard.settings', $group))
        ->post(route('groups.invites.store', $group), [
            'max_uses' => null,
            'expires_at' => null,
        ])
        ->assertRedirect(route('groups.dashboard.settings', $group))
        ->assertSessionHasErrors('error');

    expect($group->invites()->count())->toBe(1);
});

it('does not allow static group invite links to be viewed or accepted', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_STATIC,
    ]);
    $invite = GroupInvite::query()->create([
        'group_id' => $group->id,
        'created_by' => $owner->id,
        'token' => 'staticlink',
        'is_system' => false,
    ]);

    $this->get(route('groups.invites.show', $invite->token))
        ->assertNotFound();

    $this->actingAs($viewer)
        ->post(route('groups.invites.accept', $invite->token))
        ->assertRedirect(route('groups.index'))
        ->assertSessionHasErrors('error');

    expect($group->memberships()->where('user_id', $viewer->id)->exists())->toBeFalse()
        ->and($invite->fresh()->uses)->toBe(0);
});

it('rejects invite max uses above the supported limit', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
    ]);

    $this->actingAs($owner)
        ->from(route('groups.dashboard.settings', $group))
        ->post(route('groups.invites.store', $group), [
            'max_uses' => 100000,
            'expires_at' => null,
        ])
        ->assertRedirect(route('groups.dashboard.settings', $group))
        ->assertSessionHasErrors(['max_uses']);

    expect($group->invites()->count())->toBe(1);
});

it('rejects invite max uses values that contain non-digit characters', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_COMMUNITY,
    ]);

    $this->actingAs($owner)
        ->from(route('groups.dashboard.settings', $group))
        ->post(route('groups.invites.store', $group), [
            'max_uses' => '1e3',
            'expires_at' => null,
        ])
        ->assertRedirect(route('groups.dashboard.settings', $group))
        ->assertSessionHasErrors(['max_uses']);

    expect($group->invites()->count())->toBe(1);
});
