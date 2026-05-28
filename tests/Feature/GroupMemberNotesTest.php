<?php

use App\Models\Character;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupUserNote;
use App\Models\User;
use App\Support\Input\TextInputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGroupMemberNotesContext(): array
{
    $owner = User::factory()->create();
    $moderator = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->open()->create([
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

    $secondaryCharacter = Character::factory()->create([
        'user_id' => $member->id,
        'name' => 'Alt Vanguard',
        'world' => 'Ragnarok',
        'datacenter' => 'Chaos',
        'is_primary' => false,
        'verified_at' => now(),
    ]);

    $primaryCharacter = Character::factory()->create([
        'user_id' => $member->id,
        'name' => 'Main Vanguard',
        'world' => 'Cerberus',
        'datacenter' => 'Chaos',
        'is_primary' => true,
        'verified_at' => now(),
    ]);

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

    $sharedGroup = Group::factory()->open()->create();
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
        ->assertJsonPath('member.characters.0.id', $primaryCharacter->id)
        ->assertJsonPath('member.characters.0.name', 'Main Vanguard')
        ->assertJsonPath('member.characters.0.world', 'Cerberus')
        ->assertJsonPath('member.characters.0.datacenter', 'Chaos')
        ->assertJsonPath('member.characters.0.is_primary', true)
        ->assertJsonPath('member.characters.1.id', $secondaryCharacter->id)
        ->assertJsonPath('member.characters.1.name', 'Alt Vanguard')
        ->assertJsonPath('member.characters.1.world', 'Ragnarok')
        ->assertJsonPath('member.characters.1.datacenter', 'Chaos')
        ->assertJsonPath('member.characters.1.is_primary', false)
        ->assertJsonPath('member.notes.can_view', true)
        ->assertJsonPath('member.notes.can_add', true)
        ->assertJsonPath('member.notes.current_group_count', 1)
        ->assertJsonPath('member.notes.shared_count', 1)
        ->assertJsonPath('member.notes.current_group.0.body', 'Needs clearer attendance communication.')
        ->assertJsonPath('member.notes.current_group.0.permissions.can_edit_body', true)
        ->assertJsonPath('member.notes.current_group.0.permissions.can_delete', true)
        ->assertJsonPath('member.notes.current_group.0.permissions.can_add_addendum', true)
        ->assertJsonPath('member.notes.current_group.0.addenda.0.body', 'Missed the last two call-time confirmations.')
        ->assertJsonPath('member.notes.current_group.0.addenda.0.permissions.can_edit_body', false)
        ->assertJsonPath('member.notes.current_group.0.addenda.0.permissions.can_delete', false)
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

it('sanitizes member note bodies and addenda before storing them', function () {
    extract(createGroupMemberNotesContext());

    $sanitizer = app(TextInputSanitizer::class);
    $rawBody = " Missed\u{200B}\r\n call\t times ";
    $rawAddendum = " Needs\t a\r\n follow-up ";

    $this->actingAs($moderator);

    $this->post(route('groups.members.notes.store', [
        'group' => $group->slug,
        'user' => $member->id,
    ]), [
        'severity' => GroupUserNote::SEVERITY_WARNING,
        'body' => $rawBody,
        'is_shared_with_groups' => false,
    ])->assertRedirect();

    $note = GroupUserNote::query()->sole();

    expect($note->body)->toBe($sanitizer->sanitizeMultiline($rawBody));

    $this->post(route('groups.members.notes.addenda.store', [
        'group' => $group->slug,
        'note' => $note->id,
    ]), [
        'body' => $rawAddendum,
    ])->assertRedirect();

    expect($note->fresh()->addenda()->sole()->body)->toBe($sanitizer->sanitizeMultiline($rawAddendum));
});

it('allows addendum authors to update their member note context', function () {
    extract(createGroupMemberNotesContext());

    $sanitizer = app(TextInputSanitizer::class);

    $note = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $owner->id,
        'severity' => GroupUserNote::SEVERITY_WARNING,
        'body' => 'Existing note',
        'is_shared_with_groups' => false,
    ]);

    $addendum = $note->addenda()->create([
        'author_user_id' => $moderator->id,
        'body' => 'Needs follow-up.',
    ]);

    $rawBody = " Updated\t context\r\n with details ";

    $this->actingAs($moderator)
        ->from('/groups/'.$group->slug.'/members')
        ->put(route('groups.members.notes.addenda.update', [
            'group' => $group->slug,
            'addendum' => $addendum->id,
        ]), [
            'body' => $rawBody,
        ])
        ->assertRedirect('/groups/'.$group->slug.'/members');

    expect($addendum->fresh()->body)->toBe($sanitizer->sanitizeMultiline($rawBody));
});

it('allows addendum authors to delete their member note context', function () {
    extract(createGroupMemberNotesContext());

    $note = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $owner->id,
        'severity' => GroupUserNote::SEVERITY_INFO,
        'body' => 'Existing note',
        'is_shared_with_groups' => false,
    ]);

    $addendum = $note->addenda()->create([
        'author_user_id' => $moderator->id,
        'body' => 'Context to remove.',
    ]);

    $this->actingAs($moderator)
        ->from('/groups/'.$group->slug.'/members')
        ->delete(route('groups.members.notes.addenda.destroy', [
            'group' => $group->slug,
            'addendum' => $addendum->id,
        ]))
        ->assertRedirect('/groups/'.$group->slug.'/members');

    $this->assertDatabaseMissing('group_user_note_addenda', [
        'id' => $addendum->id,
    ]);
});

it('forbids moderators from updating addenda they did not author', function () {
    extract(createGroupMemberNotesContext());

    $note = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $owner->id,
        'severity' => GroupUserNote::SEVERITY_WARNING,
        'body' => 'Existing note',
        'is_shared_with_groups' => false,
    ]);

    $addendum = $note->addenda()->create([
        'author_user_id' => $owner->id,
        'body' => 'Original context.',
    ]);

    $this->actingAs($moderator)
        ->put(route('groups.members.notes.addenda.update', [
            'group' => $group->slug,
            'addendum' => $addendum->id,
        ]), [
            'body' => 'Changed by someone else.',
        ])
        ->assertForbidden();

    expect($addendum->fresh()->body)->toBe('Original context.');
});

it('forbids moderators from deleting addenda they did not author', function () {
    extract(createGroupMemberNotesContext());

    $note = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $owner->id,
        'severity' => GroupUserNote::SEVERITY_WARNING,
        'body' => 'Existing note',
        'is_shared_with_groups' => false,
    ]);

    $addendum = $note->addenda()->create([
        'author_user_id' => $owner->id,
        'body' => 'Protected context.',
    ]);

    $this->actingAs($moderator)
        ->delete(route('groups.members.notes.addenda.destroy', [
            'group' => $group->slug,
            'addendum' => $addendum->id,
        ]))
        ->assertForbidden();

    $this->assertDatabaseHas('group_user_note_addenda', [
        'id' => $addendum->id,
        'body' => 'Protected context.',
    ]);
});

it('rejects member note and addendum bodies that exceed the configured limits', function () {
    extract(createGroupMemberNotesContext());

    $this->actingAs($moderator);

    $this->from('/groups/'.$group->slug.'/members')
        ->post(route('groups.members.notes.store', [
            'group' => $group->slug,
            'user' => $member->id,
        ]), [
            'severity' => GroupUserNote::SEVERITY_WARNING,
            'body' => str_repeat('n', GroupUserNote::BODY_MAX_LENGTH + 1),
            'is_shared_with_groups' => false,
        ])
        ->assertRedirect('/groups/'.$group->slug.'/members')
        ->assertSessionHasErrors(['body']);

    $note = GroupUserNote::create([
        'group_id' => $group->id,
        'user_id' => $member->id,
        'author_user_id' => $moderator->id,
        'severity' => GroupUserNote::SEVERITY_INFO,
        'body' => 'Existing note',
        'is_shared_with_groups' => false,
    ]);

    $this->from('/groups/'.$group->slug.'/members')
        ->post(route('groups.members.notes.addenda.store', [
            'group' => $group->slug,
            'note' => $note->id,
        ]), [
            'body' => str_repeat('a', GroupUserNote::ADDENDUM_MAX_LENGTH + 1),
        ])
        ->assertRedirect('/groups/'.$group->slug.'/members')
        ->assertSessionHasErrors(['body']);
});
