<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivitySlot;
use App\Models\ActivitySlotAssignment;
use App\Models\Character;
use App\Models\Group;
use App\Services\Groups\ActivitySlotBench;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class GroupLeaderboardController extends Controller
{
    private const CACHE_TTL_SECONDS = 86_400;

    private const CACHE_VERSION = 1;

    private const REFRESH_COOLDOWN_SECONDS = 300;

    private const HOST_SUCCESS_AUTO_WEIGHT = 0.5;

    private const HOST_SUCCESS_PRIOR_BASELINE = 0.6;

    private const HOST_SUCCESS_PRIOR_WEIGHT = 10;

    private const HOST_SUCCESS_MIN_COMPLETED_RUNS = 2;

    public function __invoke(Group $group): Response
    {
        $group->loadMissing('memberships');

        $currentUserId = auth()->id();

        if (! $group->hasMember($currentUserId)) {
            abort(403);
        }

        $cacheEntry = $this->leaderboardCacheEntry($group);

        return Inertia::render('Dashboard/Groups/Leaderboard', [
            'group' => $this->serializeNavigationGroup($group, $currentUserId),
            'leaderboard' => $cacheEntry['payload'],
            'leaderboard_cache' => $this->serializeCacheMeta($group, $cacheEntry),
        ]);
    }

    public function refresh(Group $group): RedirectResponse
    {
        $group->loadMissing('memberships');

        $currentUserId = auth()->id();

        if (! $group->hasMember($currentUserId)) {
            abort(403);
        }

        if ($this->refreshCooldownSeconds($group) > 0) {
            return redirect()
                ->route('groups.dashboard.leaderboard', $group)
                ->with('error', 'group_leaderboard_refresh_cooldown');
        }

        $cacheEntry = $this->freshLeaderboardCacheEntry($group);

        Cache::put($this->leaderboardCacheKey($group), $cacheEntry, $this->cacheExpiresAt());
        Cache::put(
            $this->refreshCooldownKey($group),
            CarbonImmutable::now()->addSeconds(self::REFRESH_COOLDOWN_SECONDS)->toIso8601String(),
            CarbonImmutable::now()->addSeconds(self::REFRESH_COOLDOWN_SECONDS),
        );

        return redirect()
            ->route('groups.dashboard.leaderboard', $group)
            ->with('success', 'group_leaderboard_refreshed');
    }

    /**
     * @return array{payload: array<string, mixed>, cached_at: string}
     */
    private function leaderboardCacheEntry(Group $group): array
    {
        $cacheKey = $this->leaderboardCacheKey($group);
        $cacheEntry = Cache::get($cacheKey);

        if ($this->isValidCacheEntry($cacheEntry)) {
            return $cacheEntry;
        }

        $cacheEntry = $this->freshLeaderboardCacheEntry($group);

        Cache::put($cacheKey, $cacheEntry, $this->cacheExpiresAt());

        return $cacheEntry;
    }

    /**
     * @return array{payload: array<string, mixed>, cached_at: string}
     */
    private function freshLeaderboardCacheEntry(Group $group): array
    {
        return [
            'payload' => $this->buildLeaderboardPayload($group),
            'cached_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    private function isValidCacheEntry(mixed $cacheEntry): bool
    {
        return is_array($cacheEntry)
            && isset($cacheEntry['payload'], $cacheEntry['cached_at'])
            && is_array($cacheEntry['payload'])
            && is_string($cacheEntry['cached_at']);
    }

    /**
     * @param  array{payload: array<string, mixed>, cached_at: string}  $cacheEntry
     * @return array<string, mixed>
     */
    private function serializeCacheMeta(Group $group, array $cacheEntry): array
    {
        $cachedAt = $this->toImmutable($cacheEntry['cached_at']);
        $cooldownSeconds = $this->refreshCooldownSeconds($group);
        $refreshAvailableAt = Cache::get($this->refreshCooldownKey($group));

        return [
            'cached_at' => $cachedAt?->toIso8601String(),
            'expires_at' => $cachedAt?->addSeconds(self::CACHE_TTL_SECONDS)->toIso8601String(),
            'refresh_cooldown_seconds' => $cooldownSeconds,
            'refresh_available_at' => $cooldownSeconds > 0 && is_string($refreshAvailableAt)
                ? $this->toImmutable($refreshAvailableAt)?->toIso8601String()
                : null,
            'can_refresh' => $cooldownSeconds === 0,
        ];
    }

    private function refreshCooldownSeconds(Group $group): int
    {
        $refreshAvailableAt = Cache::get($this->refreshCooldownKey($group));

        if (! is_string($refreshAvailableAt)) {
            return 0;
        }

        $availableAt = $this->toImmutable($refreshAvailableAt);

        if (! $availableAt) {
            return 0;
        }

        return max(0, $availableAt->getTimestamp() - CarbonImmutable::now()->getTimestamp());
    }

    private function leaderboardCacheKey(Group $group): string
    {
        return sprintf('groups:%d:leaderboard:v%d', $group->id, self::CACHE_VERSION);
    }

    private function refreshCooldownKey(Group $group): string
    {
        return sprintf('groups:%d:leaderboard-refresh-cooldown:v%d', $group->id, self::CACHE_VERSION);
    }

    private function cacheExpiresAt(): CarbonImmutable
    {
        return CarbonImmutable::now()->addSeconds(self::CACHE_TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLeaderboardPayload(Group $group): array
    {
        $participationRecords = $this->participationRecords($group);
        $raidLeaderRecords = $this->designatedSlotRecords($group, 'is_raid_leader');
        $hostRecords = $this->designatedSlotRecords($group, 'is_host');
        $completedHostRecords = $hostRecords
            ->filter(fn (array $record) => $record['activity']?->status === Activity::STATUS_COMPLETE)
            ->values();

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'summary' => [
                'total_participations' => $participationRecords->count(),
                'ranked_participants' => $participationRecords->pluck('character_id')->unique()->count(),
                'raid_leader_participations' => $raidLeaderRecords->count(),
                'host_participations' => $hostRecords->count(),
                'completed_hosted_runs' => $completedHostRecords->count(),
            ],
            'rankings' => [
                'overall' => $this->countRanking($participationRecords),
                'raid_leaders' => $this->countRanking($raidLeaderRecords),
                'hosts' => $this->countRanking($hostRecords),
                'host_success' => $this->hostSuccessRanking($completedHostRecords),
            ],
        ];
    }

    /**
     * @return Collection<int, array{activity_id: int, character_id: int, character: Character|null, activity_date: CarbonImmutable|null, source_priority: int}>
     */
    private function participationRecords(Group $group): Collection
    {
        $assignmentRecords = ActivitySlotAssignment::query()
            ->where('activity_slot_assignments.group_id', $group->id)
            ->whereHas('slot', fn ($query) => $query->where('group_key', '!=', ActivitySlotBench::GROUP_KEY))
            ->with([
                'activity:id,starts_at,status',
                'character:id,name,world,datacenter,avatar_url',
            ])
            ->get([
                'id',
                'activity_id',
                'group_id',
                'activity_slot_id',
                'character_id',
                'assigned_at',
                'ended_at',
            ])
            ->toBase()
            ->filter(fn (ActivitySlotAssignment $assignment) => $assignment->character_id !== null)
            ->map(fn (ActivitySlotAssignment $assignment) => [
                'activity_id' => (int) $assignment->activity_id,
                'character_id' => (int) $assignment->character_id,
                'character' => $assignment->character,
                'activity_date' => $this->toImmutable($assignment->activity?->starts_at ?? $assignment->assigned_at),
                'source_priority' => 0,
            ]);

        $currentSlotRecords = ActivitySlot::query()
            ->whereHas('activity', fn ($query) => $query->where('group_id', $group->id))
            ->where('group_key', '!=', ActivitySlotBench::GROUP_KEY)
            ->whereNotNull('assigned_character_id')
            ->with([
                'activity:id,group_id,starts_at,status',
                'assignedCharacter:id,name,world,datacenter,avatar_url',
            ])
            ->get([
                'id',
                'activity_id',
                'assigned_character_id',
                'updated_at',
            ])
            ->toBase()
            ->map(fn (ActivitySlot $slot) => [
                'activity_id' => (int) $slot->activity_id,
                'character_id' => (int) $slot->assigned_character_id,
                'character' => $slot->assignedCharacter,
                'activity_date' => $this->toImmutable($slot->activity?->starts_at ?? $slot->updated_at),
                'source_priority' => 1,
            ]);

        return $assignmentRecords
            ->merge($currentSlotRecords)
            ->groupBy(fn (array $record) => "{$record['activity_id']}:{$record['character_id']}")
            ->map(fn (Collection $records) => $records
                ->sort(function (array $left, array $right) {
                    $priorityComparison = $right['source_priority'] <=> $left['source_priority'];

                    if ($priorityComparison !== 0) {
                        return $priorityComparison;
                    }

                    return ($right['activity_date']?->getTimestamp() ?? 0)
                        <=> ($left['activity_date']?->getTimestamp() ?? 0);
                })
                ->first())
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{activity_id: int, character_id: int, character: Character|null, activity: Activity|null, activity_date: CarbonImmutable|null}>
     */
    private function designatedSlotRecords(Group $group, string $column): Collection
    {
        return ActivitySlot::query()
            ->whereHas('activity', fn ($query) => $query->where('group_id', $group->id))
            ->where('group_key', '!=', ActivitySlotBench::GROUP_KEY)
            ->where($column, true)
            ->whereNotNull('assigned_character_id')
            ->with([
                'activity:id,group_id,activity_type_version_id,status,starts_at,target_prog_point_key,furthest_progress_key',
                'activity.activityTypeVersion:id,prog_points',
                'assignedCharacter:id,name,world,datacenter,avatar_url',
            ])
            ->get([
                'id',
                'activity_id',
                'assigned_character_id',
                'updated_at',
                $column,
            ])
            ->toBase()
            ->map(fn (ActivitySlot $slot) => [
                'activity_id' => (int) $slot->activity_id,
                'character_id' => (int) $slot->assigned_character_id,
                'character' => $slot->assignedCharacter,
                'activity' => $slot->activity,
                'activity_date' => $this->toImmutable($slot->activity?->starts_at ?? $slot->updated_at),
            ])
            ->groupBy(fn (array $record) => "{$record['activity_id']}:{$record['character_id']}")
            ->map(fn (Collection $records) => $records->first())
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function countRanking(Collection $records): array
    {
        return $records
            ->groupBy('character_id')
            ->map(function (Collection $characterRecords) {
                $latestActivityDate = $characterRecords
                    ->pluck('activity_date')
                    ->filter()
                    ->sortDesc()
                    ->first();

                return [
                    'character' => $this->serializeCharacter($characterRecords->first()['character'] ?? null),
                    'count' => $characterRecords->count(),
                    'latest_activity_at' => $latestActivityDate?->toIso8601String(),
                ];
            })
            ->sort(fn (array $left, array $right) => $this->compareCountEntries($left, $right))
            ->values()
            ->take(10)
            ->map(fn (array $entry, int $index) => array_merge(['rank' => $index + 1], $entry))
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     * @return array<int, array<string, mixed>>
     */
    private function hostSuccessRanking(Collection $records): array
    {
        $priorBaseline = $this->hostSuccessPriorBaseline($records);
        $priorWeightedSuccesses = $priorBaseline * self::HOST_SUCCESS_PRIOR_WEIGHT;

        return $records
            ->groupBy('character_id')
            ->map(function (Collection $hostRecords) use ($priorWeightedSuccesses) {
                $documentedSuccesses = 0;
                $autoSuccesses = 0;
                $failedRuns = 0;

                foreach ($hostRecords as $record) {
                    $activity = $record['activity'] ?? null;

                    if (! $activity instanceof Activity) {
                        $failedRuns++;

                        continue;
                    }

                    $outcome = $this->activityHostSuccessOutcome($activity);

                    if ($outcome === 'documented_success') {
                        $documentedSuccesses++;
                    } elseif ($outcome === 'auto_success') {
                        $autoSuccesses++;
                    } else {
                        $failedRuns++;
                    }
                }

                $hostedRuns = $hostRecords->count();
                $successfulRuns = $documentedSuccesses + $autoSuccesses;
                $weightedSuccesses = $documentedSuccesses + ($autoSuccesses * self::HOST_SUCCESS_AUTO_WEIGHT);
                $latestActivityDate = $hostRecords
                    ->pluck('activity_date')
                    ->filter()
                    ->sortDesc()
                    ->first();

                return [
                    'character' => $this->serializeCharacter($hostRecords->first()['character'] ?? null),
                    'hosted_runs' => $hostedRuns,
                    'successful_runs' => $successfulRuns,
                    'documented_successes' => $documentedSuccesses,
                    'auto_successes' => $autoSuccesses,
                    'failed_runs' => $failedRuns,
                    'weighted_successes' => (float) round($weightedSuccesses, 1),
                    'success_rate' => $hostedRuns > 0 ? (float) round(($successfulRuns / $hostedRuns) * 100, 1) : 0.0,
                    'weighted_success_rate' => $hostedRuns > 0 ? (float) round(($weightedSuccesses / $hostedRuns) * 100, 1) : 0.0,
                    'performance_score' => $hostedRuns > 0
                        ? (float) round((($weightedSuccesses + $priorWeightedSuccesses) / ($hostedRuns + self::HOST_SUCCESS_PRIOR_WEIGHT)) * 100, 1)
                        : 0.0,
                    'latest_activity_at' => $latestActivityDate?->toIso8601String(),
                ];
            })
            ->filter(fn (array $entry) => $entry['hosted_runs'] >= self::HOST_SUCCESS_MIN_COMPLETED_RUNS)
            ->sort(fn (array $left, array $right) => $this->compareSuccessEntries($left, $right))
            ->values()
            ->take(10)
            ->map(fn (array $entry, int $index) => array_merge(['rank' => $index + 1], $entry))
            ->all();
    }

    private function compareCountEntries(array $left, array $right): int
    {
        $countComparison = $right['count'] <=> $left['count'];

        if ($countComparison !== 0) {
            return $countComparison;
        }

        return strcmp($left['character']['name'] ?? '', $right['character']['name'] ?? '');
    }

    private function compareSuccessEntries(array $left, array $right): int
    {
        $scoreComparison = $right['performance_score'] <=> $left['performance_score'];

        if ($scoreComparison !== 0) {
            return $scoreComparison;
        }

        $weightedRateComparison = $right['weighted_success_rate'] <=> $left['weighted_success_rate'];

        if ($weightedRateComparison !== 0) {
            return $weightedRateComparison;
        }

        $rateComparison = $right['success_rate'] <=> $left['success_rate'];

        if ($rateComparison !== 0) {
            return $rateComparison;
        }

        $documentedComparison = $right['documented_successes'] <=> $left['documented_successes'];

        if ($documentedComparison !== 0) {
            return $documentedComparison;
        }

        $successComparison = $right['successful_runs'] <=> $left['successful_runs'];

        if ($successComparison !== 0) {
            return $successComparison;
        }

        $hostedComparison = $right['hosted_runs'] <=> $left['hosted_runs'];

        if ($hostedComparison !== 0) {
            return $hostedComparison;
        }

        return strcmp($left['character']['name'] ?? '', $right['character']['name'] ?? '');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $records
     */
    private function hostSuccessPriorBaseline(Collection $records): float
    {
        if ($records->isEmpty()) {
            return self::HOST_SUCCESS_PRIOR_BASELINE;
        }

        $weightedSuccesses = $records->sum(function (array $record): float {
            $activity = $record['activity'] ?? null;

            if (! $activity instanceof Activity) {
                return 0.0;
            }

            return match ($this->activityHostSuccessOutcome($activity)) {
                'documented_success' => 1.0,
                'auto_success' => self::HOST_SUCCESS_AUTO_WEIGHT,
                default => 0.0,
            };
        });

        return (float) $weightedSuccesses / $records->count();
    }

    private function activityHostSuccessOutcome(Activity $activity): string
    {
        if (blank($activity->target_prog_point_key)) {
            return 'auto_success';
        }

        return $this->activityReachedTarget($activity)
            ? 'documented_success'
            : 'failure';
    }

    private function activityReachedTarget(Activity $activity): bool
    {
        if (blank($activity->target_prog_point_key)) {
            return true;
        }

        if (blank($activity->furthest_progress_key)) {
            return false;
        }

        if ($activity->target_prog_point_key === $activity->furthest_progress_key) {
            return true;
        }

        $progPoints = collect($activity->activityTypeVersion?->prog_points ?? [])
            ->sortBy(fn (array $point, int $index) => (int) ($point['order'] ?? $index + 1))
            ->values();
        $targetIndex = $progPoints->search(fn (array $point) => ($point['key'] ?? null) === $activity->target_prog_point_key);
        $furthestIndex = $progPoints->search(fn (array $point) => ($point['key'] ?? null) === $activity->furthest_progress_key);

        if ($targetIndex === false || $furthestIndex === false) {
            return false;
        }

        return $furthestIndex >= $targetIndex;
    }

    /**
     * @return array{id: int|null, name: string, world: string|null, datacenter: string|null, avatar_url: string|null}
     */
    private function serializeCharacter(?Character $character): array
    {
        return [
            'id' => $character?->id,
            'name' => $character?->name ?? 'Unknown Character',
            'world' => $character?->world,
            'datacenter' => $character?->datacenter,
            'avatar_url' => $character?->avatar_url,
        ];
    }

    private function toImmutable(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (filled($value)) {
            return CarbonImmutable::parse($value);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNavigationGroup(Group $group, ?int $currentUserId): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
            'current_user_role' => $group->memberships
                ->firstWhere('user_id', $currentUserId)
                ?->role,
            'permissions' => [
                'can_manage_group' => $group->isOwnedBy($currentUserId),
                'can_manage_members' => $group->hasModeratorAccess($currentUserId),
                'can_manage_discovery' => $group->hasAdminAccess($currentUserId),
                'can_manage_activities' => $group->hasModeratorAccess($currentUserId),
                'can_view_members' => true,
                'can_review_membership_applications' => $group->usesMembershipApplications() && $group->hasModeratorAccess($currentUserId),
                'can_manage_membership_application_form' => $group->usesMembershipApplications() && $group->hasAdminAccess($currentUserId),
            ],
        ];
    }
}
