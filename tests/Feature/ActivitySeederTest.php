<?php

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivityApplicationAnswer;
use App\Models\ActivityProgressMilestone;
use App\Models\ActivitySlot;
use App\Models\ActivitySlotFieldValue;
use App\Models\ActivityType;
use App\Models\Group;
use App\Models\GroupMembershipApplication;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('seeds activities with slots, field values, milestones, and applications', function () {
    $this->seed(UserSeeder::class);
    $this->seed(ProductionSeeder::class);
    $this->seed(GroupSeeder::class);

    $applicationGroupCount = Group::query()
        ->where('join_mode', Group::JOIN_MODE_APPLICATION)
        ->count();
    $staticApplicationGroupCount = Group::query()
        ->where('group_type', Group::TYPE_STATIC)
        ->where('join_mode', Group::JOIN_MODE_APPLICATION)
        ->count();

    expect($applicationGroupCount)->toBeGreaterThan(0)
        ->and($staticApplicationGroupCount)->toBeGreaterThan(0)
        ->and(Group::query()
            ->where('join_mode', Group::JOIN_MODE_APPLICATION)
            ->whereNotNull('membership_application_schema')
            ->count())->toBe($applicationGroupCount)
        ->and(GroupMembershipApplication::query()->count())->toBeGreaterThan(0);

    $this->seed(ActivitySeeder::class);

    $activityCount = Activity::query()->count();
    $completeCount = Activity::query()->where('status', Activity::STATUS_COMPLETE)->count();
    $minStartsAt = Activity::query()->min('starts_at');
    $maxStartsAt = Activity::query()->max('starts_at');
    $distinctIntensities = Activity::query()->distinct()->pluck('intensity');
    $distinctRunStyles = Activity::query()->distinct()->pluck('run_style');
    $startHours = Activity::query()->pluck('starts_at')->map(fn ($startsAt) => Carbon::parse($startsAt)->hour);
    $beginnerFriendlyCount = Activity::query()->where('beginner_friendly', true)->count();
    $minItemLevelCount = Activity::query()->whereNotNull('min_item_level')->count();
    $assignedSlotsByActivity = ActivitySlot::query()
        ->whereNotNull('assigned_character_id')
        ->get(['activity_id', 'group_key', 'is_host', 'is_raid_leader'])
        ->groupBy('activity_id');
    $completedActivitiesWithProgress = Activity::query()
        ->where('status', Activity::STATUS_COMPLETE)
        ->whereNotNull('furthest_progress_key')
        ->whereNotNull('progress_recorded_at')
        ->count();
    $completedActivitiesWithMissedTargets = Activity::query()
        ->where('status', Activity::STATUS_COMPLETE)
        ->whereNotNull('target_prog_point_key')
        ->with('activityTypeVersion:id,prog_points')
        ->get(['id', 'activity_type_version_id', 'target_prog_point_key', 'furthest_progress_key'])
        ->filter(function (Activity $activity): bool {
            $progPoints = collect($activity->activityTypeVersion?->prog_points ?? [])
                ->filter(fn ($point): bool => is_array($point) && filled($point['key'] ?? null))
                ->values();

            $targetIndex = $progPoints->search(fn (array $point): bool => ($point['key'] ?? null) === $activity->target_prog_point_key);
            $furthestIndex = $progPoints->search(fn (array $point): bool => ($point['key'] ?? null) === $activity->furthest_progress_key);

            return $targetIndex !== false && ($furthestIndex === false || $furthestIndex < $targetIndex);
        })
        ->count();
    $designationCharacterCountsByGroup = ActivitySlot::query()
        ->join('activities', 'activities.id', '=', 'activity_slots.activity_id')
        ->whereNotNull('activity_slots.assigned_character_id')
        ->where(fn ($query) => $query
            ->where('activity_slots.is_host', true)
            ->orWhere('activity_slots.is_raid_leader', true))
        ->get(['activities.group_id', 'activity_slots.assigned_character_id'])
        ->groupBy('group_id')
        ->map(fn ($slots) => $slots->pluck('assigned_character_id')->unique()->count());
    $futureActivity = Activity::query()
        ->whereNot('status', Activity::STATUS_COMPLETE)
        ->with('activityTypeVersion')
        ->firstOrFail();

    expect($activityCount)->toBeBetween(6000, 8000)
        ->and($completeCount)->toBeBetween(2400, 3200)
        ->and(Carbon::parse($minStartsAt)->greaterThanOrEqualTo(now()->subDays(90)->startOfDay()))->toBeTrue()
        ->and(Carbon::parse($maxStartsAt)->lessThanOrEqualTo(now()->addDays(90)->endOfDay()))->toBeTrue()
        ->and($distinctIntensities->count())->toBe(3)
        ->and($distinctRunStyles->count())->toBeGreaterThanOrEqual(5)
        ->and($startHours->contains(fn (int $hour) => $hour < 17))->toBeTrue()
        ->and($startHours->contains(fn (int $hour) => $hour >= 18))->toBeTrue()
        ->and($beginnerFriendlyCount)->toBeGreaterThan(0)
        ->and($minItemLevelCount)->toBeGreaterThan(0)
        ->and($assignedSlotsByActivity->count())->toBe($activityCount)
        ->and($completedActivitiesWithProgress)->toBeGreaterThan(0)
        ->and($completedActivitiesWithMissedTargets)->toBeGreaterThan(0)
        ->and($designationCharacterCountsByGroup->isNotEmpty())->toBeTrue()
        ->and($designationCharacterCountsByGroup->every(fn (int $count): bool => $count <= 10))->toBeTrue()
        ->and(ActivitySlot::query()->where('activity_id', $futureActivity->id)->count())->toBeGreaterThan(0)
        ->and(ActivitySlotFieldValue::query()
            ->whereIn('activity_slot_id', ActivitySlot::query()->where('activity_id', $futureActivity->id)->pluck('id'))
            ->count())->toBeGreaterThan(0)
        ->and(ActivityProgressMilestone::query()->where('activity_id', $futureActivity->id)->count())->toBeGreaterThanOrEqual(0)
        ->and(ActivityApplication::query()->count())->toBeGreaterThan(0)
        ->and(ActivityApplicationAnswer::query()->count())->toBeGreaterThan(0);

    foreach ($assignedSlotsByActivity as $activitySlots) {
        $hostCount = $activitySlots
            ->filter(fn (object $slot): bool => (bool) $slot->is_host)
            ->count();

        expect($hostCount)->toBeBetween(1, 2);

        foreach ($activitySlots->groupBy('group_key') as $partySlots) {
            expect($partySlots->filter(fn (object $slot): bool => (bool) $slot->is_raid_leader)->count())
                ->toBeGreaterThanOrEqual(1);
        }
    }

    $completeActivity = Activity::query()
        ->where('status', Activity::STATUS_COMPLETE)
        ->whereHas('progressMilestones')
        ->first();

    if ($completeActivity) {
        expect(
            ActivityProgressMilestone::query()
                ->where('activity_id', $completeActivity->id)
                ->where('kills', '>', 0)
                ->exists()
        )->toBeTrue();
    }
});

it('does not seed a duplicate custom notes application field for chaotic activities', function () {
    $this->seed(ProductionSeeder::class);

    $chaotic = ActivityType::query()
        ->where('slug', 'cloud-of-darkness-chaotic')
        ->with('currentPublishedVersion')
        ->firstOrFail();

    $applicationFieldKeys = collect($chaotic->currentPublishedVersion?->application_schema ?? [])
        ->pluck('key')
        ->filter()
        ->values()
        ->all();

    expect($applicationFieldKeys)->not->toContain('notes');
});
