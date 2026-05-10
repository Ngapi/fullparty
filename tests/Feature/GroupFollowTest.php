<?php

use App\Models\AuditLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows a signed in user to follow and unfollow a public group', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);
    $follower = User::factory()->create();

    $this->actingAs($follower)
        ->post(route('groups.follow', $group))
        ->assertRedirect();

    expect($group->fresh()->followers()->where('users.id', $follower->id)->exists())->toBeTrue()
        ->and((bool) $group->fresh()->followers()->where('users.id', $follower->id)->first()->pivot->notifications_enabled)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'group.followed')->where('scope_id', $group->id)->exists())->toBeTrue();

    $this->actingAs($follower)
        ->delete(route('groups.unfollow', $group))
        ->assertRedirect();

    expect($group->fresh()->followers()->where('users.id', $follower->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'group.unfollowed')->where('scope_id', $group->id)->exists())->toBeTrue();
});

it('auto follows members when they join and removes the follow when they leave', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post(route('groups.join', $group))
        ->assertRedirect(route('groups.show', $group));

    expect($group->fresh()->followers()->where('users.id', $member->id)->exists())->toBeTrue();

    $this->actingAs($member)
        ->from(route('groups.show', $group))
        ->post(route('groups.leave', $group))
        ->assertRedirect(route('groups.show', $group));

    expect($group->fresh()->followers()->where('users.id', $member->id)->exists())->toBeFalse();
});

it('can redirect dashboard leave requests away from the dashboard', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()
        ->public()
        ->withMember($member)
        ->create([
            'owner_id' => $owner->id,
        ]);

    $this->actingAs($member)
        ->from(route('groups.dashboard', $group))
        ->post(route('groups.leave', $group), [
            'redirect_to' => 'profile',
        ])
        ->assertRedirect(route('groups.show', $group));
});

it('does not allow members to unfollow while they still belong to the group', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);
    $member = User::factory()->create();

    $this->actingAs($member)
        ->post(route('groups.join', $group))
        ->assertRedirect();

    $this->actingAs($member)
        ->delete(route('groups.unfollow', $group))
        ->assertRedirect()
        ->assertSessionHasErrors(['error']);

    expect($group->fresh()->followers()->where('users.id', $member->id)->exists())->toBeTrue();
});

it('allows followed users and members to mute or re-enable group notifications', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);
    $member = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($member)
        ->post(route('groups.join', $group))
        ->assertRedirect();

    $this->actingAs($follower)
        ->post(route('groups.follow', $group))
        ->assertRedirect();

    $this->actingAs($member)
        ->patch(route('groups.follow-notifications.update', $group), [
            'enabled' => false,
        ])
        ->assertRedirect();

    $this->actingAs($follower)
        ->patch(route('groups.follow-notifications.update', $group), [
            'enabled' => false,
        ])
        ->assertRedirect();

    expect((bool) $group->fresh()->followers()->where('users.id', $member->id)->first()->pivot->notifications_enabled)->toBeFalse()
        ->and((bool) $group->fresh()->followers()->where('users.id', $follower->id)->first()->pivot->notifications_enabled)->toBeFalse();

    $this->actingAs($member)
        ->patch(route('groups.follow-notifications.update', $group), [
            'enabled' => true,
        ])
        ->assertRedirect();

    expect((bool) $group->fresh()->followers()->where('users.id', $member->id)->first()->pivot->notifications_enabled)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'group.notifications.muted')->where('scope_id', $group->id)->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'group.notifications.enabled')->where('scope_id', $group->id)->exists())->toBeTrue();
});

it('exposes profile follow, leave, and notification actions for the current viewer', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);
    $viewer = User::factory()->create();

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.permissions.can_join', true)
            ->where('group.permissions.can_follow', true)
            ->where('group.permissions.can_toggle_notifications', false)
            ->where('group.follow.is_following', false));

    $this->actingAs($viewer)
        ->post(route('groups.follow', $group))
        ->assertRedirect();

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.permissions.can_join', true)
            ->where('group.permissions.can_follow', false)
            ->where('group.permissions.can_unfollow', true)
            ->where('group.permissions.can_leave', false)
            ->where('group.permissions.can_toggle_notifications', true)
            ->where('group.follow.is_following', true)
            ->where('group.follow.notifications_enabled', true));

    $this->patch(route('groups.follow-notifications.update', $group), [
        'enabled' => false,
    ])->assertRedirect();

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.follow.notifications_enabled', false));

    $this->post(route('groups.join', $group))
        ->assertRedirect(route('groups.show', $group));

    $this->get(route('groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('group.permissions.can_join', false)
            ->where('group.permissions.can_follow', false)
            ->where('group.permissions.can_unfollow', false)
            ->where('group.permissions.can_leave', true)
            ->where('group.permissions.can_toggle_notifications', true)
            ->where('group.follow.is_following', true)
            ->where('group.follow.notifications_enabled', false));
});
