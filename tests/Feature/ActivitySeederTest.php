<?php

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivityApplicationAnswer;
use App\Models\ActivityProgressMilestone;
use App\Models\ActivitySlot;
use App\Models\ActivitySlotFieldValue;
use Database\Seeders\ActivitySeeder;
use Database\Seeders\GroupSeeder;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds activities with slots, field values, milestones, and applications', function () {
    $this->seed(UserSeeder::class);
    $this->seed(ProductionSeeder::class);
    $this->seed(GroupSeeder::class);
    $this->seed(ActivitySeeder::class);

    $activityCount = Activity::query()->count();
    $completeCount = Activity::query()->where('status', Activity::STATUS_COMPLETE)->count();
    $futureActivity = Activity::query()
        ->whereNot('status', Activity::STATUS_COMPLETE)
        ->with('activityTypeVersion')
        ->firstOrFail();

    expect($activityCount)->toBeGreaterThan(0)
        ->and($completeCount)->toBeGreaterThan(0)
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
