<?php

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivitySlot;
use App\Models\ActivityType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('renders the run discovery scaffold with lookup data', function () {
    Group::factory()->public()->create([
        'name' => 'Test Group',
        'slug' => 'test-group',
    ]);

    createRunDiscoveryClass([
        'name' => 'White Mage',
        'shorthand' => 'WHM',
        'role' => 'healer',
        'icon_url' => 'https://example.com/icons/whm.png',
    ]);

    createRunDiscoveryActivityType([
        'slug' => 'forked-tower',
        'draft_name' => ['en' => 'Forked Tower'],
        'draft_difficulty' => ActivityType::DIFFICULTY_CHAOTIC,
        'draft_prog_points' => [
            [
                'key' => 'clear',
                'label' => ['en' => 'Clear'],
                'order' => 1,
            ],
        ],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard.runs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Runs/Index')
            ->has('lookups.activity_types', 1)
            ->has('lookups.class_options', 1)
            ->has('lookups.groups', 1)
            ->where('lookups.activity_types.0.value', 'forked-tower')
            ->where('lookups.activity_types.0.label', 'Forked Tower')
            ->where('lookups.activity_types.0.prog_points.0.value', 'clear')
            ->where('lookups.class_options.0.key', 'WHM')
            ->where('lookups.class_options.0.icon_url', 'https://example.com/icons/whm.png')
            ->where('lookups.regions.0.value', 'NA')
            ->where('lookups.regions.0.label', 'North America')
            ->where('lookups.datacenters.0.value', 'Aether')
            ->where('lookups.groups.0.value', 'test-group'));
});

it('returns matching discoverable run ids for the filter payload', function () {
    $viewer = User::factory()->create();

    createRunDiscoveryClass([
        'name' => 'White Mage',
        'shorthand' => 'WHM',
        'role' => 'healer',
        'icon_url' => 'https://example.com/icons/whm.png',
    ]);

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'savage-raids',
        'draft_name' => ['en' => 'Savage Raids'],
        'draft_difficulty' => ActivityType::DIFFICULTY_SAVAGE,
        'draft_prog_points' => [
            [
                'key' => 'enrage',
                'label' => ['en' => 'Enrage'],
                'order' => 1,
            ],
        ],
    ]);

    $matchingGroup = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Light',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
            'voice_expectation' => 'preferred',
        ]);

    $matchingActivity = Activity::factory()->create([
        'group_id' => $matchingGroup->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Moon Enrage Push',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(18, 30),
        'datacenter' => 'Light',
        'intensity' => Activity::INTENSITY_MIDCORE,
        'run_style' => Activity::RUN_STYLE_PROGRESSION,
        'target_prog_point_key' => 'enrage',
        'is_public' => true,
        'needs_application' => true,
        'allow_guest_applications' => false,
    ]);

    $excludedGroup = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Aether',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
            'voice_expectation' => 'preferred',
        ]);

    Activity::factory()->create([
        'group_id' => $excludedGroup->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Moon Enrage Push',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(18, 30),
        'datacenter' => 'Aether',
        'intensity' => Activity::INTENSITY_MIDCORE,
        'run_style' => Activity::RUN_STYLE_PROGRESSION,
        'target_prog_point_key' => 'enrage',
        'is_public' => true,
        'needs_application' => true,
        'allow_guest_applications' => false,
    ]);

    $params = [
        'query' => 'Moon',
        'activity_type' => 'savage-raids',
        'prog_point' => 'enrage',
        'region' => 'EU',
        'datacenter' => 'Light',
        'group' => $matchingGroup->slug,
        'timezone' => 'Europe/London',
        'date_range' => 'next_week',
        'time_of_day' => 'evening',
        'run_style' => Activity::RUN_STYLE_PROGRESSION,
        'beginner_friendly' => false,
        'language' => 'en',
        'role_category' => 'healer',
        'class_keys' => ['WHM'],
        'group_type' => Group::TYPE_COMMUNITY,
        'application_status' => 'applications_open',
        'intensity' => Activity::INTENSITY_MIDCORE,
        'voice_expectation' => 'preferred',
    ];

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', $params))
        ->assertOk()
        ->assertJsonPath('ids', [$matchingActivity->id])
        ->assertJsonPath('items.0.id', $matchingActivity->id)
        ->assertJsonPath('items.0.title', 'Moon Enrage Push')
        ->assertJsonPath('items.0.group_name', $matchingGroup->name)
        ->assertJsonPath('items.0.can_apply', true);

    $responseImageUrl = $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', $params))
        ->json('items.0.image_url');

    expect($responseImageUrl)->toContain('/storage/runs/generated-discovery/');

    $imagePath = ltrim((string) parse_url($responseImageUrl, PHP_URL_PATH), '/');
    Storage::disk('public')->assertExists(str_replace('storage/', '', $imagePath));
});

it('treats melee-style role hints as dps slots in discovery', function () {
    $viewer = User::factory()->create();

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'melee-check',
        'draft_name' => ['en' => 'Melee Check'],
        'draft_layout_schema' => [
            'groups' => [
                [
                    'key' => 'party-a',
                    'label' => ['en' => 'Party A'],
                    'size' => 1,
                    'composition_hints' => [
                        [
                            'position' => 1,
                            'accepts' => [
                                ['type' => 'role', 'key' => 'melee'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $group = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Light',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
        ]);

    $activity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Melee Spot Open',
        'starts_at' => now()->addWeek()->startOfWeek()->addDay()->setTime(19, 0),
        'datacenter' => 'Light',
        'is_public' => true,
        'needs_application' => false,
    ]);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', [
            'timezone' => 'Europe/London',
            'date_range' => 'next_week',
            'group_type' => Group::TYPE_COMMUNITY,
            'role_category' => 'dps',
        ]))
        ->assertOk()
        ->assertJsonPath('ids', [$activity->id])
        ->assertJsonPath('items.0.id', $activity->id)
        ->assertJsonPath('items.0.role_slots.2.key', 'dps')
        ->assertJsonPath('items.0.role_slots.2.count', 1);
});

it('only exposes private moderator-visible runs to moderators of that group', function () {
    $moderator = User::factory()->create();
    $outsider = User::factory()->create();
    $activityType = createRunDiscoveryActivityType([
        'slug' => 'criterion',
        'draft_name' => ['en' => 'Criterion'],
    ]);

    $group = Group::factory()
        ->private()
        ->create([
            'owner_id' => $moderator->id,
            'datacenter' => 'Light',
        ]);

    $privatePlannedActivity = Activity::factory()
        ->private()
        ->create([
            'group_id' => $group->id,
            'activity_type_id' => $activityType->id,
            'activity_type_version_id' => $activityType->current_published_version_id,
            'status' => Activity::STATUS_PLANNED,
            'starts_at' => now()->addWeek()->startOfWeek()->addDays(1)->setTime(19, 0),
            'datacenter' => 'Light',
        ]);

    $params = [
        'timezone' => 'Europe/London',
        'date_range' => 'next_week',
        'group_type' => Group::TYPE_COMMUNITY,
    ];

    $this->actingAs($outsider)
        ->getJson(route('dashboard.runs.discover', $params))
        ->assertOk()
        ->assertJsonPath('ids', [])
        ->assertJsonPath('items', []);

    $this->actingAs($moderator)
        ->getJson(route('dashboard.runs.discover', $params))
        ->assertOk()
        ->assertJsonPath('ids', [$privatePlannedActivity->id])
        ->assertJsonPath('items.0.id', $privatePlannedActivity->id)
        ->assertJsonPath('items.0.can_apply', true);
});

it('only returns runs the viewer can actually act on', function () {
    $viewer = User::factory()->create();
    $viewerCharacter = Character::factory()->primary()->create([
        'user_id' => $viewer->id,
    ]);

    $otherUser = User::factory()->create();
    $otherCharacter = Character::factory()->primary()->create([
        'user_id' => $otherUser->id,
    ]);

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'chaotic-alliance',
        'draft_name' => ['en' => 'Chaotic Alliance'],
    ]);

    $group = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Light',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
        ]);

    $applicationRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Open Applications',
        'starts_at' => now()->addWeek()->startOfWeek()->addDay()->setTime(19, 0),
        'datacenter' => 'Light',
        'is_public' => true,
        'needs_application' => true,
    ]);

    $directJoinRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Open Self Assign',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(19, 0),
        'datacenter' => 'Light',
        'is_public' => true,
        'needs_application' => false,
    ]);

    ActivitySlot::factory()->create([
        'activity_id' => $directJoinRun->id,
        'group_key' => 'party-a',
        'slot_key' => 'party-a-slot-open',
    ]);

    $filledDirectJoinRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Filled Self Assign',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(3)->setTime(19, 0),
        'datacenter' => 'Light',
        'is_public' => true,
        'needs_application' => false,
    ]);

    ActivitySlot::factory()->assignedTo($otherCharacter)->create([
        'activity_id' => $filledDirectJoinRun->id,
        'group_key' => 'party-a',
        'slot_key' => 'party-a-slot-filled',
    ]);

    $filledDirectJoinRun->slots()->update([
        'assigned_character_id' => $otherCharacter->id,
        'assigned_by_user_id' => $otherUser->id,
    ]);

    $alreadyAppliedRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Already Applied',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(4)->setTime(19, 0),
        'datacenter' => 'Light',
        'is_public' => true,
        'needs_application' => true,
    ]);

    ActivityApplication::factory()->create([
        'activity_id' => $alreadyAppliedRun->id,
        'user_id' => $viewer->id,
        'selected_character_id' => $viewerCharacter->id,
    ]);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', [
            'timezone' => 'Europe/London',
            'date_range' => 'next_week',
            'group_type' => Group::TYPE_COMMUNITY,
        ]))
        ->assertOk()
        ->assertJsonPath('ids', [$applicationRun->id, $directJoinRun->id])
        ->assertJsonPath('items.0.id', $applicationRun->id)
        ->assertJsonPath('items.0.can_apply', true)
        ->assertJsonPath('items.1.id', $directJoinRun->id)
        ->assertJsonPath('items.1.can_apply', false);
});

it('matches time-of-day filters in the user timezone instead of the app timezone', function () {
    config(['app.timezone' => 'UTC']);

    $viewer = User::factory()->create();
    createRunDiscoveryClass([
        'name' => 'White Mage',
        'shorthand' => 'WHM',
        'role' => 'healer',
    ]);

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'ultimate-raids',
        'draft_name' => ['en' => 'Ultimate Raids'],
    ]);

    $group = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Aether',
            'preferred_languages' => ['en'],
        ]);

    $activity = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Timezone Check',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(20, 0),
        'datacenter' => 'Aether',
        'is_public' => true,
    ]);

    $afternoonParams = [
        'timezone' => 'America/New_York',
        'date_range' => 'next_week',
        'time_of_day' => 'afternoon',
        'language' => 'en',
        'group_type' => Group::TYPE_COMMUNITY,
    ];

    $eveningParams = [
        'timezone' => 'America/New_York',
        'date_range' => 'next_week',
        'time_of_day' => 'evening',
        'language' => 'en',
        'group_type' => Group::TYPE_COMMUNITY,
    ];

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', $afternoonParams))
        ->assertOk()
        ->assertJsonPath('ids', [$activity->id])
        ->assertJsonPath('items.0.id', $activity->id);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', $eveningParams))
        ->assertOk()
        ->assertJsonPath('ids', [])
        ->assertJsonPath('items', []);
});

it('uses the updated evening and night boundaries for time-of-day filtering', function () {
    config(['app.timezone' => 'UTC']);

    $viewer = User::factory()->create();

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'boundary-check',
        'draft_name' => ['en' => 'Boundary Check'],
    ]);

    $group = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Light',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
        ]);

    $eveningRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Late Evening Run',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(22, 0),
        'datacenter' => 'Light',
        'is_public' => true,
    ]);

    $nightRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Night Run',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(3)->setTime(23, 0),
        'datacenter' => 'Light',
        'is_public' => true,
    ]);

    $morningRun = Activity::factory()->create([
        'group_id' => $group->id,
        'activity_type_id' => $activityType->id,
        'activity_type_version_id' => $activityType->current_published_version_id,
        'status' => Activity::STATUS_SCHEDULED,
        'title' => 'Morning Run',
        'starts_at' => now()->addWeek()->startOfWeek()->addDays(4)->setTime(5, 0),
        'datacenter' => 'Light',
        'is_public' => true,
    ]);

    $commonParams = [
        'timezone' => 'UTC',
        'date_range' => 'next_week',
        'group_type' => Group::TYPE_COMMUNITY,
        'language' => 'en',
    ];

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', array_merge($commonParams, [
            'time_of_day' => 'evening',
        ])))
        ->assertOk()
        ->assertJsonPath('ids', [$eveningRun->id]);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', array_merge($commonParams, [
            'time_of_day' => 'night',
        ])))
        ->assertOk()
        ->assertJsonPath('ids', [$nightRun->id]);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', array_merge($commonParams, [
            'time_of_day' => 'morning',
        ])))
        ->assertOk()
        ->assertJsonPath('ids', [$morningRun->id]);
});

it('paginates discovery results at ten runs per page', function () {
    $viewer = User::factory()->create();

    $activityType = createRunDiscoveryActivityType([
        'slug' => 'extreme-trials',
        'draft_name' => ['en' => 'Extreme Trials'],
    ]);

    $group = Group::factory()
        ->public()
        ->create([
            'datacenter' => 'Light',
            'group_type' => Group::TYPE_COMMUNITY,
            'preferred_languages' => ['en'],
        ]);

    foreach (range(1, 11) as $index) {
        Activity::factory()->create([
            'group_id' => $group->id,
            'activity_type_id' => $activityType->id,
            'activity_type_version_id' => $activityType->current_published_version_id,
            'status' => Activity::STATUS_SCHEDULED,
            'title' => 'Run '.$index,
            'starts_at' => now()->addWeek()->startOfWeek()->addDays(2)->setTime(18, 0)->addMinutes($index),
            'datacenter' => 'Light',
            'is_public' => true,
        ]);
    }

    $params = [
        'timezone' => 'Europe/London',
        'date_range' => 'next_week',
        'group_type' => Group::TYPE_COMMUNITY,
        'page' => 1,
    ];

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', $params))
        ->assertOk()
        ->assertJsonCount(10, 'ids')
        ->assertJsonCount(10, 'items')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 11);

    $this->actingAs($viewer)
        ->getJson(route('dashboard.runs.discover', array_merge($params, ['page' => 2])))
        ->assertOk()
        ->assertJsonCount(1, 'ids')
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 11);
});

function createRunDiscoveryActivityType(array $attributes = []): ActivityType
{
    return ActivityType::factory()
        ->withPublishedVersion()
        ->create($attributes)
        ->fresh('currentPublishedVersion');
}

function createRunDiscoveryClass(array $attributes = []): CharacterClass
{
    return CharacterClass::query()->create(array_merge([
        'name' => 'White Mage',
        'shorthand' => 'WHM',
        'icon_url' => null,
        'flaticon_url' => null,
        'role' => 'healer',
    ], $attributes));
}
