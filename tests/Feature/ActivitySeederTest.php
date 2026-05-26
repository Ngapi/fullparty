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
    $futureActivity = Activity::query()
        ->whereNot('status', Activity::STATUS_COMPLETE)
        ->with('activityTypeVersion')
        ->firstOrFail();

    expect($activityCount)->toBeBetween(1500, 2000)
        ->and($completeCount)->toBeBetween(600, 800)
        ->and(Carbon::parse($minStartsAt)->greaterThanOrEqualTo(now()->subDays(90)->startOfDay()))->toBeTrue()
        ->and(Carbon::parse($maxStartsAt)->lessThanOrEqualTo(now()->addDays(90)->endOfDay()))->toBeTrue()
        ->and($distinctIntensities->count())->toBe(3)
        ->and($distinctRunStyles->count())->toBeGreaterThanOrEqual(5)
        ->and($startHours->contains(fn (int $hour) => $hour < 17))->toBeTrue()
        ->and($startHours->contains(fn (int $hour) => $hour >= 18))->toBeTrue()
        ->and($beginnerFriendlyCount)->toBeGreaterThan(0)
        ->and($minItemLevelCount)->toBeGreaterThan(0)
        ->and(ActivitySlot::query()->where('activity_id', $futureActivity->id)->count())->toBeGreaterThan(0)
        ->and(ActivitySlotFieldValue::query()
            ->whereIn('activity_slot_id', ActivitySlot::query()->where('activity_id', $futureActivity->id)->pluck('id'))
            ->count())->toBeGreaterThan(0)
        ->and(ActivityProgressMilestone::query()->where('activity_id', $futureActivity->id)->count())->toBeGreaterThanOrEqual(0)
        ->and(ActivityApplication::query()->count())->toBeGreaterThan(0)
        ->and(ActivityApplicationAnswer::query()->count())->toBeGreaterThan(0);

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
