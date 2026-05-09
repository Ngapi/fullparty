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
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the group dashboard with activity-driven overview data', function () {
    $owner = User::factory()->create();
    $group = Group::factory()->public()->create([
        'owner_id' => $owner->id,
        'description' => 'Late-night raid group for progression and cleanup.',
        'discord_invite_url' => 'https://discord.gg/fullparty',
    ]);

    Character::factory()->primary()->create([
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
        'updated_at' => now()->subHours(2),
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
        'updated_at' => now()->subHour(),
    ]);

    $completeActivity = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'organized_by_user_id' => $owner->id,
        'title' => 'Cleanup Clear',
        'allow_guest_applications' => false,
        'is_public' => true,
        'updated_at' => now()->subDays(2),
    ]);

    ActivityApplication::factory()->create([
        'activity_id' => $plannedActivity->id,
        'user_id' => $member->id,
        'selected_character_id' => $memberCharacter->id,
    ]);

    $this->actingAs($owner);

    $response = $this->get(route('groups.dashboard', $group));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/Dashboard')
            ->where('group.name', $group->name)
            ->where('group.stats.activity_count', 3)
            ->where('group.stats.planned_count', 1)
            ->where('group.stats.assigned_count', 1)
            ->where('group.stats.completed_count', 1)
            ->where('group.stats.open_application_count', 1)
            ->where('group.stats.public_activity_count', 2)
            ->where('group.member_role_breakdown.owner', 1)
            ->where('group.member_role_breakdown.moderator', 1)
            ->where('group.member_role_breakdown.member', 1)
            ->has('group.activity_status_breakdown', 8)
            ->has('group.recent_activities', 3)
            ->where('group.recent_activities.0.id', $assignedActivity->id)
            ->where('group.recent_activities.0.title', 'Roster Locked')
            ->where('group.recent_activities.1.id', $plannedActivity->id)
            ->where('group.recent_activities.1.activity_type.slug', 'chaotic-alliance')
            ->where('group.recent_activities.1.application_count', 1)
            ->where('group.recent_activities.1.slot_count', 4)
            ->where('group.recent_activities.2.id', $completeActivity->id)
        );
});
