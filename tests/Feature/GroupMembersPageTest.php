<?php

use App\Models\Activity;
use App\Models\ActivitySlot;
use App\Models\ActivitySlotAssignment;
use App\Models\Character;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Services\Groups\ActivitySlotBench;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows group-specific completed run participation counts for members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['name' => 'Completed Runner']);
    $group = Group::factory()->create(['owner_id' => $owner->id]);

    GroupMembership::query()->firstOrCreate(
        ['group_id' => $group->id, 'user_id' => $member->id],
        ['role' => GroupMembership::ROLE_MEMBER, 'joined_at' => now()->subMonth()]
    );

    $character = Character::factory()->primary()->create([
        'user_id' => $member->id,
        'name' => 'Runner Main',
    ]);
    $replacementCharacter = Character::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Replacement Runner',
    ]);

    $firstCompleted = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'starts_at' => now()->subDays(4),
    ]);
    $secondCompleted = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
        'starts_at' => now()->subDays(2),
    ]);
    $scheduled = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
        'status' => Activity::STATUS_SCHEDULED,
    ]);
    $cancelled = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
        'status' => Activity::STATUS_CANCELLED,
    ]);
    $missingCompleted = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
    ]);
    $endedCompleted = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
    ]);
    $benchCompleted = Activity::factory()->complete()->create([
        'group_id' => $group->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
    ]);
    $otherGroup = Group::factory()->create();
    $otherGroupCompleted = Activity::factory()->complete()->create([
        'group_id' => $otherGroup->id,
        'activity_type_id' => $firstCompleted->activity_type_id,
        'activity_type_version_id' => $firstCompleted->activity_type_version_id,
    ]);

    createAssignmentForMemberCount($firstCompleted, $character, ActivitySlotAssignment::STATUS_CHECKED_IN);
    createAssignmentForMemberCount($firstCompleted, $character, ActivitySlotAssignment::STATUS_LATE);
    assignCurrentSlotForMemberCount($secondCompleted, $character);
    createAssignmentForMemberCount($scheduled, $character, ActivitySlotAssignment::STATUS_CHECKED_IN);
    createAssignmentForMemberCount($cancelled, $character, ActivitySlotAssignment::STATUS_CHECKED_IN);
    createAssignmentForMemberCount($missingCompleted, $character, ActivitySlotAssignment::STATUS_MISSING);
    createAssignmentForMemberCount($endedCompleted, $character, ActivitySlotAssignment::STATUS_CHECKED_IN, endedAt: now()->subDay());
    assignCurrentSlotForMemberCount($endedCompleted, $replacementCharacter);
    createAssignmentForMemberCount($benchCompleted, $character, ActivitySlotAssignment::STATUS_CHECKED_IN, isBench: true);
    createAssignmentForMemberCount($otherGroupCompleted, $character, ActivitySlotAssignment::STATUS_CHECKED_IN);

    $this->actingAs($owner)
        ->get(route('groups.dashboard.members', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Groups/Members/Index')
            ->where('members.1.name', 'Completed Runner')
            ->where('members.1.participated_run_count', 2)
        );
});

function assignCurrentSlotForMemberCount(Activity $activity, Character $character): void
{
    $activity->slots()
        ->where('group_key', '!=', ActivitySlotBench::GROUP_KEY)
        ->orderBy('sort_order')
        ->firstOrFail()
        ->update([
            'assigned_character_id' => $character->id,
            'assigned_by_user_id' => $character->user_id,
        ]);
}

function createAssignmentForMemberCount(
    Activity $activity,
    Character $character,
    string $attendanceStatus,
    ?Carbon $endedAt = null,
    bool $isBench = false,
): ActivitySlotAssignment {
    $slot = $isBench
        ? ActivitySlot::factory()->create([
            'activity_id' => $activity->id,
            'group_key' => ActivitySlotBench::GROUP_KEY,
            'slot_key' => ActivitySlotBench::GROUP_KEY.'-test-slot',
        ])
        : ($activity->slots()->where('group_key', '!=', ActivitySlotBench::GROUP_KEY)->first()
            ?? ActivitySlot::factory()->create(['activity_id' => $activity->id]));

    return ActivitySlotAssignment::query()->create([
        'activity_id' => $activity->id,
        'group_id' => $activity->group_id,
        'activity_slot_id' => $slot->id,
        'character_id' => $character->id,
        'attendance_status' => $attendanceStatus,
        'assigned_at' => $activity->starts_at ?? now(),
        'ended_at' => $endedAt,
    ]);
}
