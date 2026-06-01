<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityTypeVersion;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Input\TextInputSanitizer;
use App\Support\Notifications\NotificationPreferenceChannel;
use App\Support\Notifications\NotificationTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createGroupUpdateActivityType(User $owner): array
{
    $type = ActivityType::factory()->create([
        'created_by_user_id' => $owner->id,
    ]);

    $version = ActivityTypeVersion::factory()->create([
        'activity_type_id' => $type->id,
        'published_by_user_id' => $owner->id,
        'application_schema' => [],
        'slot_schema' => [],
        'layout_schema' => [
            'groups' => [],
        ],
    ]);

    $type->update([
        'current_published_version_id' => $version->id,
    ]);

    return compact('type', 'version');
}

it('notifies moderators and the owner when a run is created in draft state', function () {
    $owner = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $actor = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $otherModerator = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->createMany([
        [
            'user_id' => $actor->id,
            'role' => GroupMembership::ROLE_MODERATOR,
            'joined_at' => now(),
        ],
        [
            'user_id' => $otherModerator->id,
            'role' => GroupMembership::ROLE_MODERATOR,
            'joined_at' => now(),
        ],
    ]);

    extract(createGroupUpdateActivityType($owner));

    $this->actingAs($actor)
        ->post(route('groups.dashboard.activities.store', $group), [
            'activity_type_id' => $type->id,
            'status' => Activity::STATUS_DRAFT,
            'title' => 'Draft Run',
            'is_public' => true,
            'needs_application' => true,
            'allow_guest_applications' => false,
        ])
        ->assertRedirect(route('groups.dashboard.activities.index', $group));

    $event = NotificationEvent::query()->where('type', 'groups.run_draft')->sole();

    $recipientIds = UserNotification::query()
        ->where('notification_event_id', $event->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($recipientIds)->toBe(
        collect([$owner->id, $otherModerator->id])->sort()->values()->all()
    )
        ->and(NotificationDelivery::query()->count())->toBe(0);
});

it('notifies members when a run is created in scheduled state', function () {
    $owner = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $member = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $mutedMember = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);
    $group->memberships()->create([
        'user_id' => $mutedMember->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
        'notifications_enabled' => false,
    ]);

    extract(createGroupUpdateActivityType($owner));

    $this->actingAs($owner)
        ->post(route('groups.dashboard.activities.store', $group), [
            'activity_type_id' => $type->id,
            'status' => Activity::STATUS_SCHEDULED,
            'title' => 'Public Schedule',
            'is_public' => true,
            'needs_application' => true,
            'allow_guest_applications' => false,
        ])
        ->assertRedirect(route('groups.dashboard.activities.index', $group));

    $event = NotificationEvent::query()->where('type', 'groups.run_scheduled')->sole();

    $recipientIds = UserNotification::query()
        ->where('notification_event_id', $event->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($recipientIds)->toBe(
        collect([$member->id])->sort()->values()->all()
    );
});

it('notifies members when a draft run is later scheduled', function () {
    $owner = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $moderator = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $member = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->createMany([
        [
            'user_id' => $moderator->id,
            'role' => GroupMembership::ROLE_MODERATOR,
            'joined_at' => now(),
        ],
        [
            'user_id' => $member->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ],
    ]);

    extract(createGroupUpdateActivityType($owner));

    $activity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $moderator->id,
        'status' => Activity::STATUS_DRAFT,
        'title' => 'Draft Into Scheduled',
    ]);

    $this->actingAs($moderator)
        ->post(route('groups.dashboard.activities.schedule', [
            'group' => $group,
            'activity' => $activity,
        ]))
        ->assertRedirect(route('groups.dashboard.activities.show', [
            'group' => $group,
            'activity' => $activity,
        ]));

    $event = NotificationEvent::query()->where('type', 'groups.run_scheduled')->latest('id')->sole();

    $recipientIds = UserNotification::query()
        ->where('notification_event_id', $event->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($recipientIds)->toBe(
        collect([$owner->id, $member->id])->sort()->values()->all()
    )
        ->and($activity->fresh()->status)->toBe(Activity::STATUS_SCHEDULED);
});

it('allows members to mute and unmute group notifications', function () {
    $member = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $group = Group::factory()
        ->open()
        ->withMember($member)
        ->create();

    $this->actingAs($member)
        ->from(route('groups.dashboard', $group))
        ->patch(route('groups.notifications.update', $group), [
            'enabled' => false,
        ])
        ->assertRedirect(route('groups.dashboard', $group))
        ->assertSessionDoesntHaveErrors();

    expect($group->memberships()->where('user_id', $member->id)->first()?->notifications_enabled)->toBeFalse();

    $this->actingAs($member)
        ->from(route('groups.dashboard', $group))
        ->patch(route('groups.notifications.update', $group), [
            'enabled' => true,
        ])
        ->assertRedirect(route('groups.dashboard', $group))
        ->assertSessionDoesntHaveErrors();

    expect($group->memberships()->where('user_id', $member->id)->first()?->notifications_enabled)->toBeTrue();
});

it('allows group notification overrides to opt into and out of group run posts', function () {
    $owner = User::factory()->create();
    $globallyMutedMember = User::factory()->create([
        'group_update_notifications' => false,
    ]);
    $globallyEnabledMember = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $groupMutedMember = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->createMany([
        [
            'user_id' => $globallyMutedMember->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ],
        [
            'user_id' => $globallyEnabledMember->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ],
        [
            'user_id' => $groupMutedMember->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
            'notifications_enabled' => false,
        ],
    ]);

    $this->actingAs($globallyMutedMember)
        ->patch(route('groups.notifications.update', $group), [
            'enabled' => true,
            'notification_preferences' => [
                NotificationTopic::GROUP_RUN_POSTS => [
                    NotificationPreferenceChannel::IN_APP => true,
                ],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($globallyEnabledMember)
        ->patch(route('groups.notifications.update', $group), [
            'enabled' => true,
            'notification_preferences' => [
                NotificationTopic::GROUP_RUN_POSTS => [
                    NotificationPreferenceChannel::IN_APP => false,
                ],
            ],
        ])
        ->assertRedirect();

    extract(createGroupUpdateActivityType($owner));

    $this->actingAs($owner)
        ->post(route('groups.dashboard.activities.store', $group), [
            'activity_type_id' => $type->id,
            'status' => Activity::STATUS_SCHEDULED,
            'title' => 'Override Schedule',
            'is_public' => true,
            'needs_application' => true,
            'allow_guest_applications' => false,
        ])
        ->assertRedirect(route('groups.dashboard.activities.index', $group));

    $event = NotificationEvent::query()->where('type', 'groups.run_scheduled')->sole();

    $recipientIds = UserNotification::query()
        ->where('notification_event_id', $event->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($recipientIds)->toBe([$globallyMutedMember->id]);
});

it('notifies the affected user when they are promoted and demoted', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $member,
        ]), [
            'role' => GroupMembership::ROLE_MODERATOR,
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $member,
        ]), [
            'role' => GroupMembership::ROLE_MEMBER,
        ])
        ->assertRedirect();

    expect(NotificationEvent::query()->where('type', 'groups.member_promoted')->count())->toBe(1)
        ->and(NotificationEvent::query()->where('type', 'groups.member_demoted')->count())->toBe(1)
        ->and(UserNotification::query()->pluck('user_id')->unique()->all())->toBe([$member->id]);
});

it('allows admins to promote members to moderators and demote moderators to members', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->createMany([
        [
            'user_id' => $admin->id,
            'role' => GroupMembership::ROLE_ADMIN,
            'joined_at' => now(),
        ],
        [
            'user_id' => $member->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ],
    ]);

    $this->actingAs($admin)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $member,
        ]), [
            'role' => GroupMembership::ROLE_MODERATOR,
        ])
        ->assertRedirect();

    expect($group->memberships()->where('user_id', $member->id)->sole()->role)
        ->toBe(GroupMembership::ROLE_MODERATOR);

    $this->actingAs($admin)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $member,
        ]), [
            'role' => GroupMembership::ROLE_MEMBER,
        ])
        ->assertRedirect();

    expect($group->memberships()->where('user_id', $member->id)->sole()->role)
        ->toBe(GroupMembership::ROLE_MEMBER);
});

it('prevents admins from managing owner or admin-level roles', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $otherAdmin = User::factory()->create();
    $moderator = User::factory()->create();
    $member = User::factory()->create();

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->createMany([
        [
            'user_id' => $admin->id,
            'role' => GroupMembership::ROLE_ADMIN,
            'joined_at' => now(),
        ],
        [
            'user_id' => $otherAdmin->id,
            'role' => GroupMembership::ROLE_ADMIN,
            'joined_at' => now(),
        ],
        [
            'user_id' => $moderator->id,
            'role' => GroupMembership::ROLE_MODERATOR,
            'joined_at' => now(),
        ],
        [
            'user_id' => $member->id,
            'role' => GroupMembership::ROLE_MEMBER,
            'joined_at' => now(),
        ],
    ]);

    $this->actingAs($admin)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $member,
        ]), [
            'role' => GroupMembership::ROLE_ADMIN,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $moderator,
        ]), [
            'role' => GroupMembership::ROLE_ADMIN,
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $otherAdmin,
        ]), [
            'role' => GroupMembership::ROLE_MODERATOR,
        ])
        ->assertForbidden();

    expect($group->memberships()->where('user_id', $member->id)->sole()->role)->toBe(GroupMembership::ROLE_MEMBER)
        ->and($group->memberships()->where('user_id', $moderator->id)->sole()->role)->toBe(GroupMembership::ROLE_MODERATOR)
        ->and($group->memberships()->where('user_id', $otherAdmin->id)->sole()->role)->toBe(GroupMembership::ROLE_ADMIN);
});

it('treats admin as an elevated promotion tier with the same notification flow', function () {
    $owner = User::factory()->create();
    $moderator = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->put(route('groups.members.update', [
            'group' => $group,
            'user' => $moderator,
        ]), [
            'role' => GroupMembership::ROLE_ADMIN,
        ])
        ->assertRedirect();

    expect($group->memberships()->where('user_id', $moderator->id)->sole()->role)->toBe(GroupMembership::ROLE_ADMIN)
        ->and(NotificationEvent::query()->where('type', 'groups.member_promoted')->count())->toBe(1)
        ->and(UserNotification::query()->pluck('user_id')->unique()->all())->toBe([$moderator->id]);
});

it('notifies both sides when ownership is transferred', function () {
    $owner = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $newOwner = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $newOwner->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('groups.transfer-ownership', $group), [
            'user_id' => $newOwner->id,
        ])
        ->assertRedirect();

    expect(NotificationEvent::query()->where('type', 'groups.ownership_transferred_to_you')->count())->toBe(1)
        ->and(NotificationEvent::query()->where('type', 'groups.ownership_transferred_from_you')->count())->toBe(1)
        ->and(UserNotification::query()->whereHas('notificationEvent', fn ($q) => $q->where('type', 'groups.ownership_transferred_to_you'))->sole()->user_id)->toBe($newOwner->id)
        ->and(UserNotification::query()->whereHas('notificationEvent', fn ($q) => $q->where('type', 'groups.ownership_transferred_from_you'))->sole()->user_id)->toBe($owner->id);
});

it('notifies moderators when a user joins or leaves and notifies the user when they are banned', function () {
    $owner = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $moderator = User::factory()->create([
        'group_update_notifications' => true,
    ]);
    $member = User::factory()->create([
        'group_update_notifications' => true,
    ]);

    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);

    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->post(route('groups.join', $group))
        ->assertRedirect();

    $joinEvent = NotificationEvent::query()->where('type', 'groups.member_joined')->sole();

    $joinRecipientIds = UserNotification::query()
        ->where('notification_event_id', $joinEvent->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($joinRecipientIds)->toBe(
        collect([$owner->id, $moderator->id])->sort()->values()->all()
    );

    $this->actingAs($member)
        ->post(route('groups.leave', $group))
        ->assertRedirect();

    $leaveEvent = NotificationEvent::query()->where('type', 'groups.member_left')->sole();

    $leaveRecipientIds = UserNotification::query()
        ->where('notification_event_id', $leaveEvent->id)
        ->pluck('user_id')
        ->sort()
        ->values()
        ->all();

    expect($leaveRecipientIds)->toBe(
        collect([$owner->id, $moderator->id])->sort()->values()->all()
    );

    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('groups.members.ban', [
            'group' => $group,
            'user' => $member,
        ]), [])
        ->assertRedirect();

    $banEvent = NotificationEvent::query()->where('type', 'groups.member_banned')->sole();
    $banNotification = UserNotification::query()->where('notification_event_id', $banEvent->id)->sole();

    expect($banNotification->user_id)->toBe($member->id);
});

it('sanitizes ban reasons before persisting moderation actions', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->open()->create([
        'owner_id' => $owner->id,
    ]);
    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now(),
    ]);

    $sanitizer = app(TextInputSanitizer::class);
    $rawReason = " Repea\u{200B}ted\r\n disruptions\t ";

    $this->actingAs($owner)
        ->post(route('groups.members.ban', [
            'group' => $group,
            'user' => $member,
        ]), [
            'reason' => $rawReason,
        ])
        ->assertRedirect();

    expect($group->bans()->sole()->reason)->toBe($sanitizer->sanitizeMultiline($rawReason));
});
