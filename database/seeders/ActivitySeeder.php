<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivityType;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\PhantomJob;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ActivitySeeder extends Seeder
{
    /**
     * @var array<string, bool>
     */
    private array $generatedSecretKeys = [];

    /**
     * Seed the application's activities.
     */
    public function run(): void
    {
        $groups = Group::query()
            ->with([
                'memberships.user.primaryCharacter',
            ])
            ->orderBy('id')
            ->get();

        if ($groups->count() < 20) {
            throw new RuntimeException('Expected at least 20 groups before seeding activities.');
        }

        $activityTypes = ActivityType::query()
            ->with('currentPublishedVersion')
            ->get()
            ->keyBy('slug');

        $forkedTower = $activityTypes->get('forked-tower');
        $chaotic = $activityTypes->get('cloud-of-darkness-chaotic');
        $savage = $activityTypes->get('savage-raids');

        if (! $forkedTower?->currentPublishedVersion || ! $chaotic?->currentPublishedVersion || ! $savage?->currentPublishedVersion) {
            throw new RuntimeException('Expected published activity types for forked tower, chaotic, and savage.');
        }

        $characterClasses = CharacterClass::query()->get()->keyBy('id');
        $characterClassesByShorthand = $characterClasses->mapWithKeys(
            fn (CharacterClass $characterClass) => [$characterClass->shorthand => $characterClass]
        );
        $phantomJobs = PhantomJob::query()->get()->keyBy('id');

        $activityTypeContexts = [
            'forked-tower' => $this->buildActivityTypeContext($forkedTower, $characterClassesByShorthand),
            'cloud-of-darkness-chaotic' => $this->buildActivityTypeContext($chaotic, $characterClassesByShorthand),
            'savage-raids' => $this->buildActivityTypeContext($savage, $characterClassesByShorthand),
        ];

        $characterLoadouts = $this->ensureCharacterLoadouts(
            $groups
                ->flatMap(fn (Group $group) => $group->memberships->map(fn (GroupMembership $membership) => $membership->user?->primaryCharacter))
                ->filter(fn (?Character $character) => $character instanceof Character)
                ->unique('id')
                ->values(),
            $characterClasses,
            $phantomJobs,
        );

        $referenceDate = now()->startOfDay();

        foreach ($groups as $group) {
            $groupMemberUsers = $group->memberships
                ->map(fn (GroupMembership $membership) => $membership->user)
                ->filter(fn (?User $user) => $user instanceof User && $user->primaryCharacter)
                ->values();

            if ($groupMemberUsers->isEmpty()) {
                continue;
            }

            $organizerPool = $group->memberships
                ->filter(fn (GroupMembership $membership) => $membership->user && $membership->user->primaryCharacter)
                ->values();

            if ($organizerPool->isEmpty()) {
                continue;
            }

            $groupCharacterLoadouts = $groupMemberUsers
                ->map(fn (User $user) => $characterLoadouts[$user->primaryCharacter->id] ?? null)
                ->filter(fn (?array $loadout) => is_array($loadout))
                ->values();

            if ($groupCharacterLoadouts->isEmpty()) {
                continue;
            }

            $futureActivityCount = $group->slug === 'ftel'
                ? 24
                : fake()->numberBetween(5, 50);

            foreach (range(1, $futureActivityCount) as $activityIndex) {
                $context = $group->slug === 'ftel'
                    ? $activityTypeContexts['forked-tower']
                    : $this->pickActivityTypeContext($activityTypeContexts);

                /** @var GroupMembership $organizerMembership */
                $organizerMembership = $organizerPool->random();
                $organizer = $organizerMembership->user;
                $startsAt = $this->futureStartsAt($activityIndex);
                $durationHours = fake()->randomElement([2.0, 2.5, 3.0, 3.5, 6.0]);
                $status = $this->resolveFutureStatus($startsAt);
                $slotGroups = $context['layout_groups'];
                $minAssignedSlots = $this->minimumAssignedSlotCount($slotGroups);
                $maxAssignedSlots = $this->maximumAssignedSlotCount($slotGroups, $minAssignedSlots);

                $activityId = $this->insertActivity([
                    'group_id' => $group->id,
                    'activity_type_id' => $context['activity_type']->id,
                    'activity_type_version_id' => $context['version']->id,
                    'organized_by_user_id' => $organizer->id,
                    'organized_by_character_id' => $organizer->primaryCharacter->id,
                    'status' => $status,
                    'title' => $this->activityTitleForType($context['activity_type']->slug),
                    'description' => fake()->sentence(),
                    'notes' => fake()->boolean(35) ? fake()->paragraph() : null,
                    'starts_at' => $startsAt,
                    'duration_hours' => $durationHours,
                    'datacenter' => $group->datacenter,
                    'intensity' => Activity::INTENSITY_CASUAL,
                    'min_item_level' => null,
                    'beginner_friendly' => false,
                    'run_style' => Activity::RUN_STYLE_PROGRESSION,
                    'target_prog_point_key' => $this->pickTargetProgPointKey($context['prog_points']),
                    'is_public' => $group->is_public ? fake()->boolean(80) : fake()->boolean(35),
                    'needs_application' => true,
                    'allow_guest_applications' => false,
                    'created_at' => $startsAt->copy()->subDays(fake()->numberBetween(3, 14)),
                    'updated_at' => $startsAt->copy()->subHours(fake()->numberBetween(1, 48)),
                ]);

                $this->seedActivitySlotsAndMilestones(
                    activityId: $activityId,
                    organizerUserId: $organizer->id,
                    context: $context,
                    characterLoadouts: $groupCharacterLoadouts,
                    minimumAssignments: $minAssignedSlots,
                    maximumAssignments: $maxAssignedSlots,
                    isComplete: false,
                );

                $this->seedApplicationsForActivity(
                    activityId: $activityId,
                    startsAt: $startsAt,
                    organizedByUserId: $organizer->id,
                    activityTypeVersionSchema: $context['application_schema'],
                    groupMemberUsers: $groupMemberUsers,
                    organizerPool: $organizerPool,
                    characterLoadouts: $characterLoadouts,
                );
            }

            $historicalActivityCount = $group->slug === 'ftel'
                ? 12
                : fake()->numberBetween(4, 6);

            foreach (range(1, $historicalActivityCount) as $activityIndex) {
                $context = $group->slug === 'ftel'
                    ? $activityTypeContexts['forked-tower']
                    : $this->pickActivityTypeContext($activityTypeContexts);

                /** @var GroupMembership $organizerMembership */
                $organizerMembership = $organizerPool->random();
                $organizer = $organizerMembership->user;
                $startsAt = $this->historicalStartsAt($referenceDate, $activityIndex);
                $durationHours = fake()->randomElement([2.0, 2.5, 3.0, 3.5, 6.0]);
                $slotGroups = $context['layout_groups'];
                $minAssignedSlots = $this->historicalMinimumAssignedSlotCount($slotGroups);
                $maxAssignedSlots = $this->historicalMaximumAssignedSlotCount($slotGroups, $minAssignedSlots);

                $activityId = $this->insertActivity([
                    'group_id' => $group->id,
                    'activity_type_id' => $context['activity_type']->id,
                    'activity_type_version_id' => $context['version']->id,
                    'organized_by_user_id' => $organizer->id,
                    'organized_by_character_id' => $organizer->primaryCharacter->id,
                    'status' => Activity::STATUS_COMPLETE,
                    'title' => $this->historicalActivityTitleForType($context['activity_type']->slug),
                    'description' => fake()->sentence(),
                    'notes' => fake()->boolean(30) ? fake()->paragraph() : null,
                    'starts_at' => $startsAt,
                    'duration_hours' => $durationHours,
                    'datacenter' => $group->datacenter,
                    'intensity' => Activity::INTENSITY_CASUAL,
                    'min_item_level' => null,
                    'beginner_friendly' => false,
                    'run_style' => Activity::RUN_STYLE_PROGRESSION,
                    'target_prog_point_key' => $this->pickTargetProgPointKey($context['prog_points']),
                    'is_public' => $group->is_public ? fake()->boolean(80) : fake()->boolean(35),
                    'needs_application' => true,
                    'allow_guest_applications' => false,
                    'is_completed' => true,
                    'completed_at' => $startsAt->copy()->addMinutes((int) round($durationHours * 60)),
                    'created_at' => $startsAt->copy()->subDays(fake()->numberBetween(7, 28)),
                    'updated_at' => $startsAt->copy()->subHours(fake()->numberBetween(2, 72)),
                ]);

                $this->seedActivitySlotsAndMilestones(
                    activityId: $activityId,
                    organizerUserId: $organizer->id,
                    context: $context,
                    characterLoadouts: $groupCharacterLoadouts,
                    minimumAssignments: $minAssignedSlots,
                    maximumAssignments: $maxAssignedSlots,
                    isComplete: true,
                );
            }
        }
    }

    /**
     * @param  Collection<int, Character>  $characters
     * @param  Collection<int, CharacterClass>  $characterClasses
     * @param  Collection<int, PhantomJob>  $phantomJobs
     * @return array<int, array<string, mixed>>
     */
    private function ensureCharacterLoadouts(
        Collection $characters,
        Collection $characterClasses,
        Collection $phantomJobs,
    ): array {
        $characters = $characters->unique('id')->values();

        if ($characters->isEmpty()) {
            return [];
        }

        $characterIds = $characters->pluck('id')->all();
        $now = now();

        $existingClassRows = DB::table('character_class_character')
            ->whereIn('character_id', $characterIds)
            ->get(['character_id', 'character_class_id', 'level', 'is_preferred'])
            ->groupBy('character_id');

        $classInserts = [];

        foreach ($characters as $character) {
            $characterClassRows = $existingClassRows->get($character->id, collect());

            if ($characterClassRows->isNotEmpty()) {
                continue;
            }

            $selectedClasses = $characterClasses
                ->shuffle()
                ->take(fake()->numberBetween(1, min(3, $characterClasses->count())))
                ->values();

            foreach ($selectedClasses as $index => $class) {
                $classInserts[] = [
                    'character_id' => $character->id,
                    'character_class_id' => $class->id,
                    'level' => fake()->numberBetween(90, 100),
                    'is_preferred' => $index === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($classInserts !== []) {
            DB::table('character_class_character')->insert($classInserts);
        }

        $existingPhantomRows = DB::table('character_phantom_job')
            ->whereIn('character_id', $characterIds)
            ->get(['character_id', 'phantom_job_id', 'current_level', 'is_preferred'])
            ->groupBy('character_id');

        $phantomInserts = [];

        foreach ($characters as $character) {
            $characterPhantomRows = $existingPhantomRows->get($character->id, collect());

            if ($characterPhantomRows->isNotEmpty()) {
                continue;
            }

            $selectedPhantoms = $phantomJobs
                ->shuffle()
                ->take(fake()->numberBetween(1, min(2, $phantomJobs->count())))
                ->values();

            foreach ($selectedPhantoms as $index => $phantomJob) {
                $phantomInserts[] = [
                    'character_id' => $character->id,
                    'phantom_job_id' => $phantomJob->id,
                    'current_level' => fake()->numberBetween(1, $phantomJob->max_level),
                    'is_preferred' => $index === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($phantomInserts !== []) {
            DB::table('character_phantom_job')->insert($phantomInserts);
        }

        $characterClassRows = DB::table('character_class_character')
            ->whereIn('character_id', $characterIds)
            ->get(['character_id', 'character_class_id', 'level', 'is_preferred'])
            ->groupBy('character_id');

        $characterPhantomRows = DB::table('character_phantom_job')
            ->whereIn('character_id', $characterIds)
            ->get(['character_id', 'phantom_job_id', 'current_level', 'is_preferred'])
            ->groupBy('character_id');

        $loadouts = [];

        foreach ($characters as $character) {
            $classes = $characterClassRows
                ->get($character->id, collect())
                ->map(function (object $row) use ($characterClasses): ?array {
                    /** @var CharacterClass|null $class */
                    $class = $characterClasses->get((int) $row->character_class_id);

                    if (! $class) {
                        return null;
                    }

                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'shorthand' => $class->shorthand,
                        'role' => $class->role,
                        'level' => (int) $row->level,
                        'is_preferred' => (bool) $row->is_preferred,
                    ];
                })
                ->filter(fn (?array $class) => is_array($class))
                ->values()
                ->all();

            $characterPhantoms = $characterPhantomRows
                ->get($character->id, collect())
                ->map(function (object $row) use ($phantomJobs): ?array {
                    /** @var PhantomJob|null $phantomJob */
                    $phantomJob = $phantomJobs->get((int) $row->phantom_job_id);

                    if (! $phantomJob) {
                        return null;
                    }

                    return [
                        'id' => $phantomJob->id,
                        'name' => $phantomJob->name,
                        'max_level' => $phantomJob->max_level,
                        'current_level' => (int) $row->current_level,
                        'is_preferred' => (bool) $row->is_preferred,
                    ];
                })
                ->filter(fn (?array $phantomJob) => is_array($phantomJob))
                ->values()
                ->all();

            $loadouts[$character->id] = [
                'id' => $character->id,
                'user_id' => $character->user_id,
                'name' => $character->name,
                'world' => $character->world,
                'datacenter' => $character->datacenter,
                'lodestone_id' => $character->lodestone_id,
                'avatar_url' => $character->avatar_url,
                'classes' => $classes,
                'preferred_class' => $this->preferredLoadoutEntry($classes),
                'phantom_jobs' => $characterPhantoms,
                'preferred_phantom_job' => $this->preferredLoadoutEntry($characterPhantoms),
            ];
        }

        return $loadouts;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertActivity(array $attributes): int
    {
        $isPublic = (bool) ($attributes['is_public'] ?? true);
        $now = now();

        return (int) DB::table('activities')->insertGetId([
            'group_id' => $attributes['group_id'],
            'activity_type_id' => $attributes['activity_type_id'],
            'activity_type_version_id' => $attributes['activity_type_version_id'],
            'organized_by_user_id' => $attributes['organized_by_user_id'],
            'organized_by_character_id' => $attributes['organized_by_character_id'],
            'status' => $attributes['status'],
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'notes' => $attributes['notes'],
            'starts_at' => $attributes['starts_at'],
            'duration_hours' => $attributes['duration_hours'],
            'datacenter' => $attributes['datacenter'],
            'intensity' => $attributes['intensity'] ?? Activity::INTENSITY_CASUAL,
            'min_item_level' => $attributes['min_item_level'] ?? null,
            'beginner_friendly' => (bool) ($attributes['beginner_friendly'] ?? false),
            'run_style' => $attributes['run_style'] ?? Activity::RUN_STYLE_PROGRESSION,
            'target_prog_point_key' => $attributes['target_prog_point_key'] ?? null,
            'is_public' => $isPublic,
            'needs_application' => (bool) ($attributes['needs_application'] ?? true),
            'allow_guest_applications' => (bool) ($attributes['allow_guest_applications'] ?? false),
            'secret_key' => $isPublic ? null : $this->generateSecretKey(),
            'settings' => $this->encodeJson([]),
            'progress_entry_mode' => $attributes['progress_entry_mode'] ?? null,
            'progress_link_url' => $attributes['progress_link_url'] ?? null,
            'progress_notes' => $attributes['progress_notes'] ?? null,
            'furthest_progress_key' => $attributes['furthest_progress_key'] ?? null,
            'furthest_progress_percent' => $attributes['furthest_progress_percent'] ?? null,
            'is_completed' => (bool) ($attributes['is_completed'] ?? false),
            'completed_at' => $attributes['completed_at'] ?? null,
            'progress_recorded_by_user_id' => $attributes['progress_recorded_by_user_id'] ?? null,
            'progress_recorded_at' => $attributes['progress_recorded_at'] ?? null,
            'created_at' => $attributes['created_at'] ?? $now,
            'updated_at' => $attributes['updated_at'] ?? $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  Collection<int, array<string, mixed>>  $characterLoadouts
     */
    private function seedActivitySlotsAndMilestones(
        int $activityId,
        int $organizerUserId,
        array $context,
        Collection $characterLoadouts,
        int $minimumAssignments,
        ?int $maximumAssignments,
        bool $isComplete,
    ): void {
        $slotDefinitions = $this->buildSlotDefinitions($context['layout_groups']);

        if ($slotDefinitions === []) {
            return;
        }

        $slotCount = count($slotDefinitions);
        $maxAssignments = min($maximumAssignments ?? $slotCount, $slotCount, $characterLoadouts->count());
        $minAssignments = min(max(0, $minimumAssignments), $maxAssignments);
        $assignmentCount = $maxAssignments === 0
            ? 0
            : fake()->numberBetween($minAssignments, $maxAssignments);

        $assignmentBySlotKey = [];

        if ($assignmentCount > 0) {
            $selectedSlotKeys = collect($slotDefinitions)
                ->shuffle()
                ->take($assignmentCount)
                ->pluck('slot_key')
                ->values();

            $selectedCharacters = $characterLoadouts
                ->shuffle()
                ->take($assignmentCount)
                ->values();

            foreach ($selectedSlotKeys as $index => $slotKey) {
                $assignmentBySlotKey[$slotKey] = $selectedCharacters[$index];
            }
        }

        $timestamps = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $slotRows = [];

        foreach ($slotDefinitions as $slotDefinition) {
            $assignedLoadout = $assignmentBySlotKey[$slotDefinition['slot_key']] ?? null;

            $slotRows[] = [
                'activity_id' => $activityId,
                'group_key' => $slotDefinition['group_key'],
                'group_label' => $this->encodeJson($slotDefinition['group_label']),
                'slot_key' => $slotDefinition['slot_key'],
                'slot_label' => $this->encodeJson($slotDefinition['slot_label']),
                'position_in_group' => $slotDefinition['position_in_group'],
                'sort_order' => $slotDefinition['sort_order'],
                'assigned_character_id' => $assignedLoadout['id'] ?? null,
                'assigned_by_user_id' => $assignedLoadout ? $organizerUserId : null,
                'is_host' => false,
                'is_raid_leader' => false,
                ...$timestamps,
            ];
        }

        DB::table('activity_slots')->insert($slotRows);

        $persistedSlots = DB::table('activity_slots')
            ->where('activity_id', $activityId)
            ->orderBy('sort_order')
            ->get(['id', 'slot_key', 'position_in_group', 'group_key']);

        $slotDefinitionMap = collect($slotDefinitions)
            ->keyBy('slot_key');

        $fieldValueRows = [];
        $compositionHintRows = [];

        foreach ($persistedSlots as $persistedSlot) {
            $slotDefinition = $slotDefinitionMap->get($persistedSlot->slot_key);
            $assignedLoadout = $assignmentBySlotKey[$persistedSlot->slot_key] ?? null;

            foreach ($context['slot_schema'] as $fieldDefinition) {
                $fieldValueRows[] = [
                    'activity_slot_id' => $persistedSlot->id,
                    'field_key' => (string) ($fieldDefinition['key'] ?? ''),
                    'field_label' => $this->encodeJson(
                        is_array($fieldDefinition['label'] ?? null)
                            ? $fieldDefinition['label']
                            : ['en' => (string) ($fieldDefinition['key'] ?? '')]
                    ),
                    'field_type' => (string) ($fieldDefinition['type'] ?? 'text'),
                    'source' => $fieldDefinition['source'] ?? null,
                    'value' => $this->encodeJson(
                        $assignedLoadout
                            ? $this->resolveSlotFieldValue(
                                slotPosition: (int) $persistedSlot->position_in_group,
                                fieldKey: (string) ($fieldDefinition['key'] ?? ''),
                                fieldSource: $fieldDefinition['source'] ?? null,
                                definition: is_array($fieldDefinition) ? $fieldDefinition : [],
                                loadout: $assignedLoadout,
                            )
                            : null
                    ),
                    ...$timestamps,
                ];
            }

            foreach (($slotDefinition['accepts'] ?? []) as $hintIndex => $accept) {
                if (! is_array($accept)) {
                    continue;
                }

                $type = (string) ($accept['type'] ?? '');
                $key = (string) ($accept['key'] ?? '');

                if ($key === '' || ! in_array($type, ['role', 'class'], true)) {
                    continue;
                }

                $characterClass = $type === 'class'
                    ? $context['classes_by_shorthand']->get($key)
                    : null;

                $compositionHintRows[] = [
                    'activity_slot_id' => $persistedSlot->id,
                    'hint_type' => $type,
                    'hint_key' => $key,
                    'role_key' => $type === 'role'
                        ? $key
                        : $this->roleKeyForClass($characterClass?->role),
                    'character_class_id' => $characterClass?->id,
                    'sort_order' => $hintIndex + 1,
                    ...$timestamps,
                ];
            }
        }

        if ($fieldValueRows !== []) {
            DB::table('activity_slot_field_values')->insert($fieldValueRows);
        }

        if ($compositionHintRows !== []) {
            DB::table('activity_slot_composition_hints')->insert($compositionHintRows);
        }

        $progressMilestoneRows = [];
        $milestones = $context['progress_milestones'];
        $lastMilestoneIndex = count($milestones) - 1;

        foreach ($milestones as $index => $milestoneDefinition) {
            $progressMilestoneRows[] = [
                'activity_id' => $activityId,
                'milestone_key' => (string) ($milestoneDefinition['key'] ?? ('milestone-'.($index + 1))),
                'milestone_label' => $this->encodeJson(
                    is_array($milestoneDefinition['label'] ?? null)
                        ? $milestoneDefinition['label']
                        : ['en' => (string) ($milestoneDefinition['key'] ?? 'Milestone')]
                ),
                'sort_order' => (int) ($milestoneDefinition['order'] ?? $index + 1),
                'kills' => $isComplete && $index === $lastMilestoneIndex ? 1 : 0,
                'best_progress_percent' => $isComplete && $index === $lastMilestoneIndex ? 100 : null,
                'source' => null,
                'notes' => null,
                ...$timestamps,
            ];
        }

        if ($progressMilestoneRows !== []) {
            DB::table('activity_progress_milestones')->insert($progressMilestoneRows);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $activityTypeVersionSchema
     * @param  Collection<int, User>  $groupMemberUsers
     * @param  Collection<int, GroupMembership>  $organizerPool
     * @param  array<int, array<string, mixed>>  $characterLoadouts
     */
    private function seedApplicationsForActivity(
        int $activityId,
        Carbon $startsAt,
        int $organizedByUserId,
        array $activityTypeVersionSchema,
        Collection $groupMemberUsers,
        Collection $organizerPool,
        array $characterLoadouts,
    ): void {
        $applicantPool = $groupMemberUsers
            ->reject(fn (User $user) => $user->id === $organizedByUserId)
            ->shuffle()
            ->values();

        if ($applicantPool->isEmpty()) {
            return;
        }

        $memberCount = $groupMemberUsers->count();
        $baseCount = (int) round($memberCount * fake()->randomFloat(2, 0.08, 0.65));
        $applicationCount = max(1, min(
            100,
            $applicantPool->count(),
            $baseCount + fake()->numberBetween(0, 12)
        ));

        $selectedApplicants = $applicantPool->take($applicationCount);
        $applicationRows = [];
        $submittedAtByUserId = [];
        $timestampsByUserId = [];

        foreach ($selectedApplicants as $user) {
            $status = fake()->randomElement([
                ActivityApplication::STATUS_PENDING,
                ActivityApplication::STATUS_PENDING,
                ActivityApplication::STATUS_PENDING,
                ActivityApplication::STATUS_APPROVED,
                ActivityApplication::STATUS_DECLINED,
            ]);

            $reviewerId = null;
            $reviewedAt = null;

            if ($status !== ActivityApplication::STATUS_PENDING) {
                /** @var GroupMembership $reviewerMembership */
                $reviewerMembership = $organizerPool->random();
                $reviewerId = $reviewerMembership->user_id;
                $reviewedAt = $startsAt->copy()->subHours(fake()->numberBetween(4, 48));
            }

            $submittedAt = $startsAt->copy()->subDays(fake()->numberBetween(1, 10));
            $selectedCharacter = $characterLoadouts[$user->primaryCharacter->id] ?? null;
            $createdAt = $submittedAt->copy()->subHours(fake()->numberBetween(0, 12));
            $updatedAt = $reviewedAt ?? $submittedAt;

            if (! is_array($selectedCharacter)) {
                continue;
            }

            $applicationRows[] = [
                'activity_id' => $activityId,
                'user_id' => $user->id,
                'selected_character_id' => $selectedCharacter['id'],
                'applicant_lodestone_id' => $selectedCharacter['lodestone_id'],
                'applicant_character_name' => $selectedCharacter['name'],
                'applicant_world' => $selectedCharacter['world'],
                'applicant_datacenter' => $selectedCharacter['datacenter'],
                'applicant_avatar_url' => $selectedCharacter['avatar_url'],
                'guest_access_token' => null,
                'status' => $status,
                'notes' => fake()->boolean(55) ? fake()->sentence() : null,
                'reviewed_by_user_id' => $reviewerId,
                'submitted_at' => $submittedAt,
                'reviewed_at' => $reviewedAt,
                'review_reason' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];

            $submittedAtByUserId[$user->id] = $submittedAt;
            $timestampsByUserId[$user->id] = [
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ];
        }

        if ($applicationRows === []) {
            return;
        }

        DB::table('activity_applications')->insert($applicationRows);

        $applications = DB::table('activity_applications')
            ->where('activity_id', $activityId)
            ->whereIn('user_id', array_keys($submittedAtByUserId))
            ->get(['id', 'user_id', 'selected_character_id'])
            ->keyBy('user_id');

        if ($applications->isEmpty() || $activityTypeVersionSchema === []) {
            return;
        }

        $answerRows = [];

        foreach ($selectedApplicants as $user) {
            $application = $applications->get($user->id);

            if (! $application) {
                continue;
            }

            $selectedCharacter = $characterLoadouts[$application->selected_character_id] ?? null;
            $timestamps = $timestampsByUserId[$user->id] ?? [
                'created_at' => $submittedAtByUserId[$user->id] ?? now(),
                'updated_at' => $submittedAtByUserId[$user->id] ?? now(),
            ];

            foreach ($activityTypeVersionSchema as $question) {
                if (! is_array($question) || blank($question['key'] ?? null)) {
                    continue;
                }

                $value = $this->generateAnswerValue($question, $selectedCharacter);

                if ($value === null) {
                    continue;
                }

                $answerRows[] = [
                    'activity_application_id' => $application->id,
                    'question_key' => (string) $question['key'],
                    'question_label' => $this->encodeJson(
                        is_array($question['label'] ?? null)
                            ? $question['label']
                            : ['en' => (string) $question['key']]
                    ),
                    'question_type' => (string) ($question['type'] ?? 'text'),
                    'source' => $question['source'] ?? null,
                    'value' => $this->encodeJson($value),
                    ...$timestamps,
                ];
            }
        }

        if ($answerRows !== []) {
            DB::table('activity_application_answers')->insert($answerRows);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $layoutGroups
     * @return array<int, array<string, mixed>>
     */
    private function buildSlotDefinitions(array $layoutGroups): array
    {
        $slotDefinitions = [];
        $sortOrder = 1;

        foreach ($layoutGroups as $groupDefinition) {
            $groupKey = (string) ($groupDefinition['key'] ?? 'group');
            $groupLabel = is_array($groupDefinition['label'] ?? null)
                ? $groupDefinition['label']
                : ['en' => $groupKey];
            $size = max(1, (int) ($groupDefinition['size'] ?? 1));
            $compositionHintsByPosition = collect($groupDefinition['composition_hints'] ?? [])
                ->filter(fn ($hint): bool => is_array($hint))
                ->keyBy(fn (array $hint): int => (int) ($hint['position'] ?? 0));

            for ($position = 1; $position <= $size; $position++) {
                $slotDefinitions[] = [
                    'group_key' => $groupKey,
                    'group_label' => $groupLabel,
                    'slot_key' => sprintf('%s-slot-%d', $groupKey, $position),
                    'slot_label' => ['en' => sprintf('%s %d', $groupLabel['en'] ?? $groupKey, $position)],
                    'position_in_group' => $position,
                    'sort_order' => $sortOrder,
                    'accepts' => $compositionHintsByPosition->get($position)['accepts'] ?? [],
                ];

                $sortOrder++;
            }
        }

        return $slotDefinitions;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function pickActivityTypeContext(array $context): array
    {
        $roll = fake()->numberBetween(1, 100);

        if ($roll <= 60) {
            return $context['savage-raids'];
        }

        if ($roll <= 80) {
            return $context['cloud-of-darkness-chaotic'];
        }

        return $context['forked-tower'];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActivityTypeContext(ActivityType $activityType, Collection $classesByShorthand): array
    {
        $version = $activityType->currentPublishedVersion;

        return [
            'activity_type' => $activityType,
            'version' => $version,
            'layout_groups' => $version->layout_schema['groups'] ?? [],
            'slot_schema' => $version->slot_schema ?? [],
            'application_schema' => $version->application_schema ?? [],
            'prog_points' => $version->prog_points ?? [],
            'progress_milestones' => $version->progress_schema['milestones'] ?? [],
            'classes_by_shorthand' => $classesByShorthand,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function minimumAssignedSlotCount(array $groups): int
    {
        $slotCount = collect($groups)->sum(fn (array $group) => (int) ($group['size'] ?? 0));

        return match (true) {
            $slotCount <= 8 => 1,
            $slotCount <= 24 => fake()->numberBetween(4, 10),
            default => fake()->numberBetween(8, 20),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function maximumAssignedSlotCount(array $groups, int $minimum): int
    {
        $slotCount = collect($groups)->sum(fn (array $group) => (int) ($group['size'] ?? 0));

        return match (true) {
            $slotCount <= 8 => $slotCount,
            $slotCount <= 24 => max($minimum, fake()->numberBetween(10, $slotCount)),
            default => max($minimum, fake()->numberBetween(20, $slotCount)),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function historicalMinimumAssignedSlotCount(array $groups): int
    {
        $slotCount = collect($groups)->sum(fn (array $group) => (int) ($group['size'] ?? 0));

        return match (true) {
            $slotCount <= 8 => max(4, min(8, $slotCount)),
            $slotCount <= 24 => fake()->numberBetween(10, min(18, $slotCount)),
            default => fake()->numberBetween(18, min(36, $slotCount)),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $groups
     */
    private function historicalMaximumAssignedSlotCount(array $groups, int $minimum): int
    {
        $slotCount = collect($groups)->sum(fn (array $group) => (int) ($group['size'] ?? 0));
        $floor = max($minimum, (int) floor($slotCount * 0.75));

        return match (true) {
            $slotCount <= 8 => $slotCount,
            $slotCount <= 24 => min($slotCount, max($floor, fake()->numberBetween($minimum, $slotCount))),
            default => min($slotCount, max($floor, fake()->numberBetween($minimum, $slotCount))),
        };
    }

    private function futureStartsAt(int $activityIndex): Carbon
    {
        $base = now()->startOfDay()->addDays(fake()->numberBetween(1, 90));
        $hour = fake()->randomElement([18, 19, 20, 21, 22]);
        $minute = fake()->randomElement([0, 15, 30, 45]);

        return $base
            ->copy()
            ->setTime($hour, $minute)
            ->addDays(intdiv($activityIndex, 4));
    }

    private function historicalStartsAt(Carbon $referenceDate, int $activityIndex): Carbon
    {
        $daysBack = fake()->numberBetween(5, 150) + intdiv($activityIndex, 3);
        $hour = fake()->randomElement([18, 19, 20, 21, 22]);
        $minute = fake()->randomElement([0, 15, 30, 45]);

        return $referenceDate
            ->copy()
            ->subDays($daysBack)
            ->setTime($hour, $minute);
    }

    private function resolveFutureStatus(Carbon $startsAt): string
    {
        $daysUntil = now()->diffInDays($startsAt, false);

        if ($daysUntil <= 2) {
            return Activity::STATUS_UPCOMING;
        }

        if ($daysUntil <= 10) {
            return Activity::STATUS_SCHEDULED;
        }

        return Activity::STATUS_PLANNED;
    }

    /**
     * @param  array<int, array<string, mixed>>  $progPoints
     */
    private function pickTargetProgPointKey(array $progPoints): ?string
    {
        if ($progPoints === []) {
            return null;
        }

        $point = collect($progPoints)->random();

        return $point['key'] ?? null;
    }

    private function activityTitleForType(string $slug): string
    {
        return match ($slug) {
            'forked-tower' => fake()->randomElement([
                'Forked Tower Weekly Clear',
                'Forked Tower Fresh Prog',
                'Forked Tower Bridges Cleanup',
                'Forked Tower Late Night Push',
                'Forked Tower Magitaur Attempts',
            ]),
            'cloud-of-darkness-chaotic' => fake()->randomElement([
                'Chaotic Alliance Fill',
                'Cloud of Darkness Reclear',
                'Chaotic Learning Run',
                'Alliance Night Pulls',
            ]),
            default => fake()->randomElement([
                'Savage Weekly Reclear',
                'Savage Prog Night',
                'Savage Alt Run',
                'Savage Static Fill',
            ]),
        };
    }

    private function historicalActivityTitleForType(string $slug): string
    {
        return match ($slug) {
            'forked-tower' => fake()->randomElement([
                'Forked Tower Reclear Night',
                'Forked Tower Archive Clear',
                'Forked Tower Weekly History',
                'Forked Tower Blood Cleanup',
            ]),
            'cloud-of-darkness-chaotic' => fake()->randomElement([
                'Chaotic Archive Clear',
                'Cloud of Darkness Farm',
                'Chaotic Reclear Night',
            ]),
            default => fake()->randomElement([
                'Savage Reclear Archive',
                'Savage Historical Farm',
                'Savage Weekly Log Run',
            ]),
        };
    }

    /**
     * @param  array<int, mixed>  $entries
     * @return array<string, mixed>|null
     */
    private function preferredLoadoutEntry(array $entries): ?array
    {
        foreach ($entries as $entry) {
            if (($entry['is_preferred'] ?? false) === true) {
                return $entry;
            }
        }

        return $entries[0] ?? null;
    }

    private function generateSecretKey(): string
    {
        do {
            $key = Str::random(40);
        } while (isset($this->generatedSecretKeys[$key]));

        $this->generatedSecretKeys[$key] = true;

        return $key;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $loadout
     * @return array<string, mixed>|null
     */
    private function resolveSlotFieldValue(
        int $slotPosition,
        string $fieldKey,
        ?string $fieldSource,
        array $definition,
        array $loadout,
    ): ?array {
        if ($fieldSource === 'character_classes') {
            $class = $this->pickAssignedClass($loadout);

            if (! $class) {
                return null;
            }

            return [
                'id' => $class['id'],
                'name' => $class['name'],
                'shorthand' => $class['shorthand'],
                'role' => $class['role'],
            ];
        }

        if ($fieldSource === 'phantom_jobs') {
            $phantomJob = $this->pickAssignedPhantomJob($loadout);

            if (! $phantomJob) {
                return null;
            }

            return [
                'id' => $phantomJob['id'],
                'name' => $phantomJob['name'],
                'max_level' => $phantomJob['max_level'],
            ];
        }

        if ($fieldSource === 'static_options') {
            $option = $this->resolveStaticOptionForSlot($slotPosition, $fieldKey, $definition);

            if (! $option) {
                return null;
            }

            return [
                'key' => $option['value'] ?? $option['key'] ?? null,
                'label' => $option['label'] ?? null,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $loadout
     * @return array<string, mixed>|null
     */
    private function pickAssignedClass(array $loadout): ?array
    {
        $classes = $loadout['classes'] ?? [];

        if ($classes === []) {
            return null;
        }

        $preferredClass = $loadout['preferred_class'] ?? null;

        if ($preferredClass && fake()->boolean(45)) {
            return $preferredClass;
        }

        return collect($classes)->random();
    }

    /**
     * @param  array<string, mixed>  $loadout
     * @return array<string, mixed>|null
     */
    private function pickAssignedPhantomJob(array $loadout): ?array
    {
        $phantomJobs = $loadout['phantom_jobs'] ?? [];

        if ($phantomJobs === []) {
            return null;
        }

        $preferredPhantomJob = $loadout['preferred_phantom_job'] ?? null;

        if ($preferredPhantomJob && fake()->boolean(45)) {
            return $preferredPhantomJob;
        }

        return collect($phantomJobs)->random();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function resolveStaticOptionForSlot(int $slotPosition, string $fieldKey, array $definition): ?array
    {
        $options = collect($definition['options'] ?? [])
            ->filter(fn ($option): bool => is_array($option));

        if ($options->isEmpty()) {
            return null;
        }

        if ($fieldKey === 'raid_position') {
            $positionKeys = ['mt', 'ot', 'h1', 'h2', 'm1', 'm2', 'r1', 'r2'];
            $targetKey = $positionKeys[max(0, $slotPosition - 1)] ?? null;

            if ($targetKey) {
                $matchingOption = $options->first(
                    fn (array $option) => ($option['value'] ?? $option['key'] ?? null) === $targetKey
                );

                if ($matchingOption) {
                    return $matchingOption;
                }
            }
        }

        return $options->random();
    }

    private function roleKeyForClass(?string $role): ?string
    {
        return match (strtolower((string) $role)) {
            'tank' => 'tank',
            'healer' => 'healer',
            default => 'dps',
        };
    }

    /**
     * @param  array<string, mixed>|null  $selectedCharacter
     */
    private function generateAnswerValue(array $question, ?array $selectedCharacter): mixed
    {
        $source = $question['source'] ?? null;
        $type = (string) ($question['type'] ?? 'text');
        $required = (bool) ($question['required'] ?? false);

        if (! $required && fake()->boolean(15)) {
            return null;
        }

        if ($source === 'character_classes') {
            $classIds = collect($selectedCharacter['classes'] ?? [])
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values();

            if ($classIds->isEmpty()) {
                return $type === 'multi_select' ? [] : null;
            }

            if ($type === 'multi_select') {
                return $classIds
                    ->shuffle()
                    ->take(fake()->numberBetween(1, min(3, $classIds->count())))
                    ->values()
                    ->all();
            }

            return $classIds->random();
        }

        if ($source === 'phantom_jobs') {
            $phantomJobIds = collect($selectedCharacter['phantom_jobs'] ?? [])
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values();

            if ($phantomJobIds->isEmpty()) {
                return $type === 'multi_select' ? [] : null;
            }

            if ($type === 'multi_select') {
                return $phantomJobIds
                    ->shuffle()
                    ->take(fake()->numberBetween(1, min(2, $phantomJobIds->count())))
                    ->values()
                    ->all();
            }

            return $phantomJobIds->random();
        }

        if ($source === 'static_options') {
            $options = collect($question['options'] ?? [])
                ->map(fn ($option) => (string) ($option['value'] ?? $option['key'] ?? ''))
                ->filter();

            if ($options->isEmpty()) {
                return $type === 'multi_select' ? [] : null;
            }

            if ($type === 'multi_select') {
                return $options
                    ->shuffle()
                    ->take(fake()->numberBetween(1, min(3, $options->count())))
                    ->values()
                    ->all();
            }

            return $options->random();
        }

        return match ($type) {
            'boolean' => fake()->boolean(),
            'textarea' => fake()->sentence(fake()->numberBetween(6, 16)),
            'url' => fake()->url(),
            'multi_select' => [],
            default => fake()->sentence(fake()->numberBetween(2, 8)),
        };
    }
}
