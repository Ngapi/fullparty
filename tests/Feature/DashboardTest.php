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

it('renders the personal dashboard summary with participation and group data', function () {
    $user = User::factory()->create([
        'name' => 'Dashboard User',
    ]);

    $character = Character::factory()->primary()->create([
        'user_id' => $user->id,
        'name' => 'Meteor Survivor',
    ]);

    $user->socialAccounts()->create([
        'provider' => 'discord',
        'provider_user_id' => 'discord-1',
        'provider_name' => 'Discord User',
    ]);
    $user->socialAccounts()->create([
        'provider' => 'xivauth',
        'provider_user_id' => 'xiv-1',
        'provider_name' => 'XIVAuth User',
    ]);

    $ownedGroup = Group::factory()->open()->create([
        'owner_id' => $user->id,
        'name' => 'Owned Group',
    ]);

    $moderatedGroup = Group::factory()->open()->create([
        'name' => 'Moderated Group',
    ]);
    $moderatedGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_MODERATOR,
        'joined_at' => now()->subDays(2),
    ]);

    $memberGroup = Group::factory()->open()->create([
        'name' => 'Member Group',
    ]);
    $memberGroup->memberships()->create([
        'user_id' => $user->id,
        'role' => GroupMembership::ROLE_MEMBER,
        'joined_at' => now()->subDay(),
    ]);

    $type = ActivityType::factory()->create([
        'slug' => 'criterion-night',
    ]);
    $version = ActivityTypeVersion::factory()->create([
        'activity_type_id' => $type->id,
        'name' => ['en' => 'Criterion Night'],
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
    ]);
    $type->update([
        'current_published_version_id' => $version->id,
    ]);

    $approvedActivity = Activity::factory()->create([
        'group_id' => $memberGroup->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'status' => Activity::STATUS_ASSIGNED,
        'title' => 'Friday Prog',
        'starts_at' => now()->addDay(),
    ]);

    $pendingActivity = Activity::factory()->create([
        'group_id' => $moderatedGroup->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'status' => Activity::STATUS_PLANNED,
        'title' => 'Saturday Fill',
        'starts_at' => now()->addDays(2),
    ]);

    $completeActivity = Activity::factory()->complete()->create([
        'group_id' => $ownedGroup->id,
        'activity_type_id' => $type->id,
        'activity_type_version_id' => $version->id,
        'title' => 'Last Week Clear',
    ]);

    $approvedApplication = ActivityApplication::factory()->approved($memberGroup->owner)->create([
        'activity_id' => $approvedActivity->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'submitted_at' => now()->subHours(5),
    ]);

    $pendingApplication = ActivityApplication::factory()->create([
        'activity_id' => $pendingActivity->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'submitted_at' => now()->subHour(),
    ]);

    $completedApplication = ActivityApplication::factory()->approved($ownedGroup->owner)->create([
        'activity_id' => $completeActivity->id,
        'user_id' => $user->id,
        'selected_character_id' => $character->id,
        'submitted_at' => now()->subDays(8),
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Dashboard')
            ->where('profile.name', 'Dashboard User')
            ->where('profile.primary_character.name', 'Meteor Survivor')
            ->where('summary.unread_notification_count', 0)
            ->where('summary.verified_character_count', 1)
            ->where('summary.connected_account_count', 2)
            ->where('summary.group_count', 3)
            ->where('summary.owned_group_count', 1)
            ->where('summary.moderated_group_count', 1)
            ->where('summary.member_group_count', 1)
            ->where('summary.active_application_count', 2)
            ->where('summary.pending_application_count', 1)
            ->where('summary.confirmed_participation_count', 1)
            ->where('summary.completed_participation_count', 1)
            ->where('setup.connected_providers', ['discord', 'xivauth'])
            ->where('groups.owned.count', 1)
            ->where('groups.moderated.count', 1)
            ->where('groups.member.count', 1)
            ->has('upcomingParticipations', 2)
            ->where('upcomingParticipations.0.id', $approvedApplication->id)
            ->where('upcomingParticipations.1.id', $pendingApplication->id)
            ->has('recentApplications', 3)
            ->where('recentApplications.0.id', $pendingApplication->id)
            ->where('recentApplications.1.id', $approvedApplication->id)
            ->where('recentApplications.2.id', $completedApplication->id)
        );
});
