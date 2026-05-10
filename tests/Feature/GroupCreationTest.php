<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
