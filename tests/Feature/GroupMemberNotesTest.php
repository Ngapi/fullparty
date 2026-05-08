<?php

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupUserNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGroupMemberNotesContext(): array
{
    $owner = User::factory()->create();
    $moderator = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    return compact('group', 'owner', 'moderator', 'member');
}

it('returns the full note payload for a group member when a moderator opens the notes modal', function () {
    extract(createGroupMemberNotesContext());

    $currentGroupNote = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $moderator->id,
        'severity' => GroupUserNote::SEVERITY_WARNING,
        'body' => 'Needs clearer attendance communication.',
        'is_shared_with_groups' => false,
    ]);

    $currentGroupNote->addenda()->create([
        'author_user_id' => $owner->id,
        'body' => 'Missed the last two call-time confirmations.',
    ]);

    $sharedGroup = Group::factory()->public()->create();
    $sharedAuthor = User::factory()->create();

    GroupUserNote::create([
        'group_id' => $sharedGroup->id,
        'user_id' => $member->id,
        'author_user_id' => $sharedAuthor->id,
        'severity' => GroupUserNote::SEVERITY_INFO,
        'body' => 'Helpful when given early role reminders.',
        'is_shared_with_groups' => true,
    ]);

    $this->actingAs($moderator);

    $response = $this->getJson(route('groups.members.notes.show', [
        'group' => $group->slug,
        'user' => $member->id,
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('member.id', $member->id)
        ->assertJsonPath('member.name', $member->name)
        ->assertJsonPath('member.notes.can_view', true)
        ->assertJsonPath('member.notes.can_add', true)
        ->assertJsonPath('member.notes.current_group_count', 1)
        ->assertJsonPath('member.notes.shared_count', 1)
        ->assertJsonPath('member.notes.current_group.0.body', 'Needs clearer attendance communication.')
        ->assertJsonPath('member.notes.current_group.0.permissions.can_edit_body', true)
        ->assertJsonPath('member.notes.current_group.0.permissions.can_delete', true)
        ->assertJsonPath('member.notes.current_group.0.permissions.can_add_addendum', true)
        ->assertJsonPath('member.notes.current_group.0.addenda.0.body', 'Missed the last two call-time confirmations.')
        ->assertJsonPath('member.notes.shared.0.body', 'Helpful when given early role reminders.')
        ->assertJsonPath('member.notes.shared.0.permissions.can_edit_body', false)
        ->assertJsonPath('member.notes.shared.0.permissions.can_delete', false)
        ->assertJsonPath('member.notes.shared.0.permissions.can_add_addendum', false)
        ->assertJsonPath('member.notes.shared.0.source_group.slug', $sharedGroup->slug);
});

it('forbids non-moderators from fetching member notes', function () {
    extract(createGroupMemberNotesContext());

    $viewer = User::factory()->create();

    $group->memberships()->create([
        'user_id' => $viewer->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $this->actingAs($viewer);

    $response = $this->getJson(route('groups.members.notes.show', [
        'group' => $group->slug,
        'user' => $member->id,
    ]));

    $response->assertForbidden();
});

it('returns not found when moderators request notes for a user outside the group and ban list', function () {
    extract(createGroupMemberNotesContext());

    $outsider = User::factory()->create();

    $this->actingAs($moderator);

    $response = $this->getJson(route('groups.members.notes.show', [
        'group' => $group->slug,
        'user' => $outsider->id,
    ]));

    $response->assertNotFound();
});
