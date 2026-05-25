<?php

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivityType;
use App\Models\ActivityTypeVersion;
use App\Models\Character;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the group dashboard with activity-driven overview data', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-27 12:00:00'));

    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'description' => 'Late-night raid group for progression and cleanup.',
        'discord_invite_url' => 'https://discord.gg/fullparty',
    ]);

    $ownerCharacter = Character::factory()->primary()->create([
        'user_id' => $owner->id,
    ]);

    $moderator = User::factory()->create();
    $group->memberships()->create([
        'user_id' => $moderator->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now()->subDay(),
    ]);

    $member = User::factory()->create();
    $group->memberships()->create([
        'user_id' => $member->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now()->subHours(3),
    ]);
    $memberCharacter = Character::factory()->primary()->create([
        'user_id' => $member->id,
    ]);

    $type = ActivityType::factory()->create([
        'created_by_user_id' => $owner->id,
        'slug' => 'chaotic-alliance',
        'draft_name' => ['en' => 'Chaotic Alliance'],
    ]);

    $version = ActivityTypeVersion::factory()->create([
        'activity_type_id' => $type->id,
        'published_by_user_id' => $owner->id,
        'layout_schema' => [
            'groups' => [
                [
                    'key' => 'party-a',
                    'label' => ['en' => 'Party A'],
                    'size' => 4,
                ],
            ],
        ],
        'slot_schema' => [],
        'progress_schema' => ['milestones' => []],
        'bench_size' => 0,
        'prog_points' => [],
    ]);

    $type->update([
        'current_published_version_id' => $version->id,
    ]);

    $plannedActivity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'status' => Activity::STATUS_PLANNED,
        'title' => 'Planning Night',
        'allow_guest_applications' => true,
        'is_public' => true,
        'starts_at' => now()->addDays(3),
        'updated_at' => now()->subHours(2),
    ]);

    ActivityApplication::factory()->create([
        'activity_id' => $plannedActivity->id,
        'user_id' => $owner->id,
        'selected_character_id' => $ownerCharacter->id,
    ]);

    $assignedActivity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'status' => Activity::STATUS_ASSIGNED,
        'title' => 'Roster Locked',
        'allow_guest_applications' => false,
        'is_public' => false,
        'starts_at' => now()->addDay(),
        'updated_at' => now()->subHour(),
    ]);

    $scheduledActivity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Soon Pull',
        'needs_application' => false,
        'allow_guest_applications' => false,
        'is_public' => true,
        'starts_at' => now()->addHours(2),
        'updated_at' => now()->subDays(3),
    ]);

    $completeActivity = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'title' => 'Cleanup Clear',
        'allow_guest_applications' => false,
        'is_public' => true,
        'starts_at' => now()->subDay(),
        'completed_at' => now()->subHour(),
        'updated_at' => now()->subMinutes(20),
    ]);

    $cancelledActivity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'status' => Activity::STATUS_CANCELLED,
        'title' => 'Cancelled Night',
        'allow_guest_applications' => false,
        'is_public' => true,
        'starts_at' => now()->subDays(2),
        'updated_at' => now()->subHours(4),
    ]);

    ActivityApplication::factory()->create([
        'activity_id' => $plannedActivity->id,
        'user_id' => $member->id,
        'selected_character_id' => $memberCharacter->id,
    ]);

    try {
        $this->actingAs($owner);

        $response = $this->get(route('groups.dashboard', $group));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Groups/CommunityDashboard')
                ->where('group.name', $group->name)
                ->where('group.stats.activity_count', 5)
                ->where('group.stats.planned_count', 1)
                ->where('group.stats.scheduled_count', 1)
                ->where('group.stats.assigned_count', 1)
                ->where('group.stats.completed_count', 1)
                ->where('group.stats.cancelled_count', 1)
                ->where('group.stats.open_application_count', 2)
                ->where('group.stats.public_activity_count', 4)
                ->where('group.follow.is_following', true)
                ->where('group.follow.notifications_enabled', true)
                ->where('group.permissions.can_leave', false)
                ->where('group.permissions.can_toggle_notifications', true)
                ->where('group.member_role_breakdown.owner', 1)
                ->where('group.member_role_breakdown.moderator', 1)
                ->where('group.member_role_breakdown.member', 1)
                ->where('group.content_summary.total_runs', 5)
                ->where('group.content_summary.status_breakdown.0.status', 'planned')
                ->where('group.content_summary.status_breakdown.0.count', 1)
                ->where('group.content_summary.status_breakdown.1.status', 'scheduled')
                ->where('group.content_summary.status_breakdown.1.count', 1)
                ->where('group.content_items.0.activity_name', $version->name['en'])
                ->where('group.content_items.0.total_runs', 5)
                ->where('group.content_items.0.completed_runs', 1)
                ->where('group.content_items.0.active_runs', 3)
                ->where('group.current_week.start_date', '2026-05-25')
                ->where('group.current_week.end_date', '2026-05-31')
                ->has('group.activity_status_breakdown', 7)
                ->has('group.current_week_activities', 5)
                ->where('group.current_week_activities.0.id', $cancelledActivity->id)
                ->where('group.current_week_activities.1.id', $completeActivity->id)
                ->where('group.current_week_activities.2.id', $scheduledActivity->id)
                ->where('group.current_week_activities.3.id', $assignedActivity->id)
                ->where('group.current_week_activities.4.id', $plannedActivity->id)
                ->has('group.upcoming_activities', 3)
                ->where('group.upcoming_activities.0.id', $scheduledActivity->id)
                ->where('group.upcoming_activities.0.title', 'Soon Pull')
                ->where('group.upcoming_activities.0.can_view_overview', true)
                ->where('group.upcoming_activities.0.can_apply', false)
                ->where('group.upcoming_activities.0.secret_key', null)
                ->where('group.upcoming_activities.0.links.view', route('groups.dashboard.activities.show', [
                    'group' => $group->slug,
                    'activity' => $scheduledActivity->id,
                ], false))
                ->where('group.upcoming_activities.0.links.apply', null)
                ->where('group.upcoming_activities.1.id', $assignedActivity->id)
                ->where('group.upcoming_activities.1.can_view_overview', true)
                ->where('group.upcoming_activities.1.secret_key', $assignedActivity->secret_key)
                ->where('group.upcoming_activities.2.id', $plannedActivity->id)
                ->where('group.upcoming_activities.2.has_existing_application', true)
                ->where('group.upcoming_activities.2.can_apply', false)
                ->where('group.upcoming_activities.2.can_view_overview', true)
                ->where('group.upcoming_activities.2.activity_type.slug', 'chaotic-alliance')
                ->where('group.upcoming_activities.2.application_count', 2)
                ->where('group.upcoming_activities.2.slot_count', 4)
                ->where('group.upcoming_activities.2.links.apply', route('groups.activities.application', [
                    'group' => $group->slug,
                    'activity' => $plannedActivity->id,
                ], false))
                ->has('group.history_activities', 2)
                ->where('group.history_activities.0.id', $completeActivity->id)
                ->where('group.history_activities.0.can_view_overview', true)
                ->where('group.history_activities.1.id', $cancelledActivity->id)
                ->where('group.history_activities.1.can_view_overview', true)
            );

        $group->followers()->syncWithoutDetaching([
            $member->id => ['notifications_enabled' => false],
        ]);

        $this->actingAs($member)
            ->get(route('groups.dashboard', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('group.stats.activity_count', 4)
                ->where('group.stats.planned_count', 0)
                ->where('group.stats.scheduled_count', 1)
                ->where('group.stats.assigned_count', 1)
                ->where('group.stats.completed_count', 1)
                ->where('group.stats.cancelled_count', 1)
                ->where('group.stats.open_application_count', 1)
                ->where('group.follow.is_following', true)
                ->where('group.follow.notifications_enabled', false)
                ->where('group.permissions.can_leave', true)
                ->where('group.permissions.can_toggle_notifications', true)
                ->where('group.content_summary.total_runs', 4)
                ->where('group.content_items.0.total_runs', 4)
                ->where('group.content_items.0.active_runs', 2)
                ->has('group.current_week_activities', 4)
                ->where('group.current_week_activities.0.id', $cancelledActivity->id)
                ->where('group.current_week_activities.1.id', $completeActivity->id)
                ->where('group.current_week_activities.2.id', $scheduledActivity->id)
                ->where('group.current_week_activities.3.id', $assignedActivity->id)
                ->has('group.upcoming_activities', 2)
                ->where('group.upcoming_activities.0.id', $scheduledActivity->id)
                ->where('group.upcoming_activities.0.can_view_overview', true)
                ->where('group.upcoming_activities.0.secret_key', null)
                ->where('group.upcoming_activities.1.id', $assignedActivity->id)
                ->where('group.upcoming_activities.1.can_view_overview', false)
                ->where('group.upcoming_activities.1.secret_key', null)
                ->has('group.history_activities', 2)
                ->where('group.history_activities.0.id', $completeActivity->id)
                ->where('group.history_activities.0.can_view_overview', true)
                ->where('group.history_activities.1.id', $cancelledActivity->id)
                ->where('group.history_activities.1.can_view_overview', true)
            );

        $follower = User::factory()->create();
        $group->followers()->syncWithoutDetaching([
            $follower->id => ['notifications_enabled' => true],
        ]);

        $this->actingAs($follower)
            ->get(route('groups.dashboard', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('group.current_user_role', null)
                ->where('group.follow.is_following', true)
                ->where('group.follow.notifications_enabled', true)
                ->where('group.permissions.can_leave', false)
                ->where('group.permissions.can_toggle_notifications', true)
                ->where('group.permissions.can_view_members', false)
                ->where('group.stats.activity_count', 3)
                ->where('group.stats.planned_count', 0)
                ->where('group.stats.scheduled_count', 1)
                ->where('group.stats.assigned_count', 0)
                ->where('group.stats.completed_count', 1)
                ->where('group.stats.cancelled_count', 1)
                ->where('group.stats.open_application_count', 1)
                ->where('group.stats.public_activity_count', 3)
                ->where('group.stats.latest_member_join_at', null)
                ->where('group.member_role_breakdown.owner', 0)
                ->where('group.member_role_breakdown.admin', 0)
                ->where('group.member_role_breakdown.moderator', 0)
                ->where('group.member_role_breakdown.member', 0)
                ->where('group.content_summary.total_runs', 3)
                ->where('group.content_items.0.total_runs', 3)
                ->where('group.content_items.0.active_runs', 1)
                ->has('group.members_preview', 0)
                ->has('group.current_week_activities', 3)
                ->where('group.current_week_activities.0.id', $cancelledActivity->id)
                ->where('group.current_week_activities.1.id', $completeActivity->id)
                ->where('group.current_week_activities.2.id', $scheduledActivity->id)
                ->has('group.upcoming_activities', 1)
                ->where('group.upcoming_activities.0.id', $scheduledActivity->id)
                ->where('group.upcoming_activities.0.can_view_overview', true)
                ->where('group.upcoming_activities.0.can_apply', false)
                ->where('group.upcoming_activities.0.secret_key', null)
                ->where('group.upcoming_activities.0.links.view', route('groups.activities.overview', [
                    'group' => $group->slug,
                    'activity' => $scheduledActivity->id,
                ], false))
                ->where('group.upcoming_activities.0.links.apply', null)
                ->has('group.history_activities', 2)
                ->where('group.history_activities.0.id', $completeActivity->id)
                ->where('group.history_activities.0.can_view_overview', true)
                ->where('group.history_activities.1.id', $cancelledActivity->id)
                ->where('group.history_activities.1.can_view_overview', true)
            );
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the static dashboard page for static groups', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->create([
        'owner_id' => $owner->id,
        'group_type' => Group::TYPE_STATIC,
    ]);

    $this->actingAs($owner)
        ->get(route('groups.dashboard', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/StaticDashboard')
            ->where('group.id', $group->id)
            ->where('group.group_type', Group::TYPE_STATIC)
        );
});
