<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\ScheduledRun;
use App\Services\AuditLogger;
use App\Services\Groups\GeneratedGroupImageService;
use App\Services\ManagedImageStorage;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSeverity;
use App\Support\Groups\GroupDiscoveryBadgePalette;
use App\Support\Input\RequestTextInputSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    private const IMAGE_DIRECTORY = 'groups';

    private const DISCOVERY_PER_PAGE = 6;

    public function __construct(
        private readonly ManagedImageStorage $managedImageStorage,
        private readonly AuditLogger $auditLogger,
        private readonly RequestTextInputSanitizer $requestTextInputSanitizer,
        private readonly GroupDiscoveryBadgePalette $groupDiscoveryBadgePalette,
        private readonly GeneratedGroupImageService $generatedGroupImageService,
    ) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();

        $ownedGroups = $user->ownedGroups()
            ->with(['memberships', 'scheduledRuns'])
            ->get()
            ->sortBy('name')
            ->values()
            ->map(fn (Group $group) => $this->serializeGroupListItem($group, $user->id));

        $moderatedGroups = $user->moderatedGroups()
            ->with(['memberships', 'scheduledRuns'])
            ->get()
            ->sortBy('name')
            ->values()
            ->map(fn (Group $group) => $this->serializeGroupListItem($group, $user->id));

        $memberGroups = $user->memberGroups()
            ->with(['memberships', 'scheduledRuns'])
            ->get()
            ->sortBy('name')
            ->values()
            ->map(fn (Group $group) => $this->serializeGroupListItem($group, $user->id));

        $discoverGroups = $this->serializePaginatedGroups(
            $this->discoverGroupsQuery($user->id, 'created_at_desc')->paginate(
                perPage: self::DISCOVERY_PER_PAGE,
                pageName: 'discover_page',
                page: (int) $request->integer('discover_page', 1)
            ),
            $user->id
        );

        return Inertia::render('Dashboard/Groups/Index', [
            'ownedGroups' => $ownedGroups,
            'moderatedGroups' => $moderatedGroups,
            'memberGroups' => $memberGroups,
            'discoverGroups' => $discoverGroups,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->requestTextInputSanitizer->sanitize($request, ['query', 'extra_tags']);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'group_type' => ['nullable', Rule::in(['all', ...Group::TYPES])],
            'experience_expectation' => ['nullable', Rule::in(config('group_discovery.experience_expectations', []))],
            'region' => ['nullable', 'string', Rule::in(array_values(array_unique(array_filter(config('datacenters.regions', [])))))],
            'size' => ['nullable', Rule::in(['1', '50', '100', '500'])],
            'sort_by' => ['nullable', Rule::in([
                'created_at_desc',
                'created_at_asc',
                'active_at_desc',
                'active_at_asc',
                'member_count_desc',
                'member_count_asc',
            ])],
            'recruiting_status' => ['nullable', Rule::in(config('group_discovery.recruiting_statuses', []))],
            'primary_focuses' => ['nullable', 'array', 'max:'.count(config('group_discovery.primary_focuses', []))],
            'primary_focuses.*' => [Rule::in(config('group_discovery.primary_focuses', []))],
            'voice_expectation' => ['nullable', Rule::in(config('group_discovery.voice_expectations', []))],
            'preferred_languages' => ['nullable', 'array', 'max:'.count(config('group_discovery.preferred_languages', []))],
            'preferred_languages.*' => [Rule::in(config('group_discovery.preferred_languages', []))],
            'active_days' => ['nullable', 'array', 'max:'.count(config('group_discovery.active_days', []))],
            'active_days.*' => [Rule::in(config('group_discovery.active_days', []))],
            'extra_tags' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $paginator = $this->discoverGroupsQuery($user->id, (string) ($validated['sort_by'] ?? 'created_at_desc'));
        $this->applyDiscoverySearchFilters($paginator, $validated);

        $paginator = $paginator->paginate(
            perPage: self::DISCOVERY_PER_PAGE,
            page: (int) ($validated['page'] ?? 1)
        );

        return response()->json($this->serializePaginatedGroups($paginator, $user->id));
    }

    public function featured(Request $request): JsonResponse
    {
        // TODO: Replace this placeholder latest-visible-groups query with a real featured-group selection algorithm.
        $groups = Group::query()
            ->visible()
            ->withCount('memberships')
            ->latest('created_at')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $groups
                ->map(fn (Group $group) => $this->serializeFeaturedGroupItem($group))
                ->values()
                ->all(),
        ]);
    }

    public function details(Request $request, Group $group): JsonResponse
    {
        abort_unless($group->is_visible, 404);

        $group->loadMissing(['owner', 'memberships', 'scheduledRuns']);

        return response()->json([
            'data' => $this->serializeGroupListItem($group, (int) $request->user()->id),
        ]);
    }

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $uploadedProfilePictureUrl = $this->managedImageStorage->uploadImageIfPresent(
            $request->file('profile_picture'),
            self::IMAGE_DIRECTORY,
            true
        );
        $uploadedBannerImageUrl = $this->managedImageStorage->uploadImageIfPresent(
            $request->file('banner_image'),
            self::IMAGE_DIRECTORY,
        );

        $group = DB::transaction(function () use ($validated, $uploadedProfilePictureUrl, $uploadedBannerImageUrl) {
            $group = Group::create([
                'owner_id' => auth()->id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'profile_picture_url' => $uploadedProfilePictureUrl,
                'banner_image_url' => $uploadedBannerImageUrl,
                'discord_invite_url' => $validated['discord_invite_url'] ?? null,
                'datacenter' => $validated['datacenter'],
                'is_public' => $validated['is_public'],
                'is_visible' => $validated['is_visible'],
                'slug' => $validated['slug'],
                'group_type' => $validated['group_type'],
                'recruiting_status' => $validated['recruiting_status'] ?? null,
                'primary_focuses' => $validated['primary_focuses'] ?? [],
                'experience_expectation' => $validated['experience_expectation'] ?? null,
                'voice_expectation' => $validated['voice_expectation'] ?? null,
                'preferred_languages' => $validated['preferred_languages'] ?? [],
                'tags' => $validated['tags'] ?? [],
                'active_timezone' => $validated['active_timezone'] ?? null,
                'active_days' => $validated['active_days'] ?? [],
                'active_start_time' => $validated['active_start_time'] ?? null,
                'active_end_time' => $validated['active_end_time'] ?? null,
            ]);

            if (blank($group->profile_picture_url)) {
                $group->profile_picture_url = $this->generatedGroupImageService->generateProfileImage(
                    $group->slug,
                    $group->name,
                    $group->datacenter,
                );
            }

            if (blank($group->banner_image_url)) {
                $group->banner_image_url = $this->generatedGroupImageService->generateBannerImage(
                    $group->slug,
                    $group->name,
                    $group->datacenter,
                );
            }

            $group->save();

            $group->memberships()->create([
                'user_id' => auth()->id(),
                'role' => GroupMembership::ROLE_OWNER,
                'joined_at' => now(),
            ]);

            if ($group->is_public) {
                $group->ensureSystemInvite();
            }

            return $group;
        });

        $this->auditLogger->log(
            action: 'group.created',
            severity: AuditSeverity::MODERATION_CHANGE,
            scopeType: AuditScope::GROUP,
            scopeId: $group->id,
            message: 'audit_log.events.group.created',
            actor: auth()->user(),
            subject: $group,
            metadata: $this->groupAuditSnapshot($group),
        );

        return redirect()->route('groups.dashboard', $group)->with('success', 'group_created');
    }

    public function destroy(Group $group): RedirectResponse
    {
        if (! $group->isOwnedBy(auth()->id())) {
            abort(403);
        }

        $groupSnapshot = [
            'id' => $group->id,
            'name' => $group->name,
            'slug' => $group->slug,
        ];

        $this->managedImageStorage->deleteManagedImage($group->profile_picture_url, self::IMAGE_DIRECTORY);
        $this->managedImageStorage->deleteManagedImage($group->banner_image_url, self::IMAGE_DIRECTORY);
        $group->delete();

        $this->auditLogger->log(
            action: 'group.deleted',
            severity: AuditSeverity::SEVERE_CHANGE,
            scopeType: AuditScope::GROUP,
            scopeId: $groupSnapshot['id'],
            message: 'audit_log.events.group.deleted',
            actor: auth()->user(),
            subject: [
                'subject_type' => Group::class,
                'subject_id' => $groupSnapshot['id'],
            ],
            metadata: $groupSnapshot,
        );

        return redirect()->route('groups.index')->with('success', 'group_deleted');
    }

    private function serializeGroupListItem(Group $group, int $currentUserId): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'profile_picture_url' => $group->profile_picture_url,
            'banner_image_url' => $group->banner_image_url,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'region' => $group->inferredRegion(),
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'slug' => $group->slug,
            'group_type' => $group->group_type,
            'recruiting_status' => $group->recruiting_status,
            'primary_focuses' => $group->primary_focuses ?? [],
            'experience_expectation' => $group->experience_expectation,
            'voice_expectation' => $group->voice_expectation,
            'preferred_languages' => $group->preferred_languages ?? [],
            'tags' => $group->tags ?? [],
            'active_timezone' => $group->active_timezone,
            'active_days' => $group->active_days ?? [],
            'active_start_time' => $group->active_start_time,
            'active_end_time' => $group->active_end_time,
            'badge_meta' => $this->groupDiscoveryBadgePalette->badgeMetaForGroup($group),
            'owner' => [
                'id' => $group->owner?->id,
                'name' => $group->owner?->name,
                'avatar_url' => $group->owner?->avatar_url,
            ],
            'links' => [
                'dashboard' => $group->hasMember($currentUserId)
                    ? route('groups.dashboard', $group, false)
                    : null,
            ],
            'current_user_role' => $group->memberships
                ->firstWhere('user_id', $currentUserId)
                ?->role,
            'stats' => [
                'member_count' => $group->memberships->count(),
                'upcoming_run_count' => $group->scheduledRuns
                    ->whereIn('status', [
                        ScheduledRun::STATUS_SCHEDULED,
                        ScheduledRun::STATUS_UPCOMING,
                        ScheduledRun::STATUS_ONGOING,
                    ])
                    ->count(),
                'run_count' => $group->scheduledRuns->count(),
                'completed_run_count' => $group->scheduledRuns
                    ->where('status', ScheduledRun::STATUS_COMPLETE)
                    ->count(),
                'latest_member_join_at' => $group->memberships->max('joined_at'),
                'last_activity_at' => $this->resolveLastActivityAt($group),
            ],
        ];
    }

    private function resolveLastActivityAt(Group $group)
    {
        $runActivity = $group->scheduledRuns->max('updated_at');

        if (! $runActivity) {
            return $group->updated_at;
        }

        return $group->updated_at && $group->updated_at->gt($runActivity)
            ? $group->updated_at
            : $runActivity;
    }

    private function discoverGroupsQuery(int $currentUserId, string $sortBy = 'created_at_desc'): Builder
    {
        $latestRunActivity = ScheduledRun::query()
            ->selectRaw('group_id, MAX(updated_at) as latest_run_activity_at')
            ->groupBy('group_id');

        $query = Group::query()
            ->select('groups.*')
            ->leftJoinSub($latestRunActivity, 'latest_run_activity', function ($join) {
                $join->on('latest_run_activity.group_id', '=', 'groups.id');
            })
            ->with(['owner', 'memberships', 'scheduledRuns'])
            ->withCount('memberships')
            ->visible();

        $this->applyDiscoverySorting($query, $sortBy);

        return $query;
    }

    private function serializePaginatedGroups(LengthAwarePaginator $paginator, int $currentUserId): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn (Group $group) => $this->serializeGroupListItem($group, $currentUserId))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function serializeFeaturedGroupItem(Group $group): array
    {
        return [
            'id' => $group->id,
            'slug' => $group->slug,
            'name' => $group->name,
            'banner_image_url' => $group->banner_image_url,
            'experience_expectation' => $group->experience_expectation,
            'experience_badge' => $group->experience_expectation !== null
                ? [
                    'value' => $group->experience_expectation,
                    'color' => $this->groupDiscoveryBadgePalette->colorFor('experience_expectations', $group->experience_expectation),
                ]
                : null,
            'preferred_languages' => $group->preferred_languages ?? [],
            'tags' => $group->tags ?? [],
            'tag_badges' => $this->groupDiscoveryBadgePalette->tagBadges($group->tags ?? []),
            'stats' => [
                'member_count' => (int) ($group->memberships_count ?? 0),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyDiscoverySearchFilters(Builder $query, array $filters): void
    {
        $searchQuery = trim((string) ($filters['query'] ?? ''));
        $groupType = (string) ($filters['group_type'] ?? 'all');
        $experienceExpectation = $filters['experience_expectation'] ?? null;
        $region = $filters['region'] ?? null;
        $size = $filters['size'] ?? null;
        $recruitingStatus = $filters['recruiting_status'] ?? null;
        $primaryFocuses = array_values(array_filter($filters['primary_focuses'] ?? [], fn ($value) => is_string($value) && $value !== ''));
        $voiceExpectation = $filters['voice_expectation'] ?? null;
        $preferredLanguages = array_values(array_filter($filters['preferred_languages'] ?? [], fn ($value) => is_string($value) && $value !== ''));
        $activeDays = array_values(array_filter($filters['active_days'] ?? [], fn ($value) => is_string($value) && $value !== ''));
        $extraTags = trim((string) ($filters['extra_tags'] ?? ''));

        if ($groupType !== '' && $groupType !== 'all') {
            $query->where('groups.group_type', $groupType);
        }

        if ($searchQuery !== '') {
            $like = '%'.mb_strtolower($searchQuery).'%';

            $query->where(function (Builder $queryBuilder) use ($like) {
                $queryBuilder
                    ->whereRaw('LOWER(groups.name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(COALESCE(groups.description, \'\')) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(groups.slug) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST(groups.tags AS TEXT)) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(CAST(groups.primary_focuses AS TEXT)) LIKE ?', [$like]);
            });
        }

        if (is_string($experienceExpectation) && $experienceExpectation !== '') {
            $query->where('groups.experience_expectation', $experienceExpectation);
        }

        if (is_string($region) && $region !== '') {
            $datacenters = collect(config('datacenters.regions', []))
                ->filter(fn (?string $mappedRegion) => $mappedRegion === $region)
                ->keys()
                ->values()
                ->all();

            if ($datacenters !== []) {
                $query->whereIn('groups.datacenter', $datacenters);
            }
        }

        if (is_string($size) && $size !== '') {
            $query->has('memberships', '>=', (int) $size);
        }

        if (is_string($recruitingStatus) && $recruitingStatus !== '') {
            $query->where('groups.recruiting_status', $recruitingStatus);
        }

        $this->applyJsonArrayAnyMatchFilter($query, 'groups.primary_focuses', $primaryFocuses);

        if (is_string($voiceExpectation) && $voiceExpectation !== '') {
            $query->where('groups.voice_expectation', $voiceExpectation);
        }

        $this->applyJsonArrayAnyMatchFilter($query, 'groups.preferred_languages', $preferredLanguages);
        $this->applyJsonArrayAnyMatchFilter($query, 'groups.active_days', $activeDays);

        if ($extraTags !== '') {
            $query->whereRaw('LOWER(CAST(groups.tags AS TEXT)) LIKE ?', ['%'.mb_strtolower($extraTags).'%']);
        }
    }

    /**
     * @param  array<int, string>  $values
     */
    private function applyJsonArrayAnyMatchFilter(Builder $query, string $column, array $values): void
    {
        if ($values === []) {
            return;
        }

        $query->where(function (Builder $queryBuilder) use ($column, $values) {
            foreach ($values as $value) {
                $queryBuilder->orWhereJsonContains($column, $value);
            }
        });
    }

    private function applyDiscoverySorting(Builder $query, string $sortBy): void
    {
        $activityExpression = 'CASE
            WHEN latest_run_activity.latest_run_activity_at IS NOT NULL
                AND latest_run_activity.latest_run_activity_at > groups.updated_at
                THEN latest_run_activity.latest_run_activity_at
            ELSE groups.updated_at
        END';

        match ($sortBy) {
            'created_at_asc' => $query
                ->orderBy('groups.created_at')
                ->orderBy('groups.name'),
            'active_at_desc' => $query
                ->orderByRaw($activityExpression.' DESC')
                ->orderBy('groups.name'),
            'active_at_asc' => $query
                ->orderByRaw($activityExpression.' ASC')
                ->orderBy('groups.name'),
            'member_count_desc' => $query
                ->orderByDesc('memberships_count')
                ->orderBy('groups.name'),
            'member_count_asc' => $query
                ->orderBy('memberships_count')
                ->orderBy('groups.name'),
            default => $query
                ->orderByDesc('groups.created_at')
                ->orderBy('groups.name'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function groupAuditSnapshot(Group $group): array
    {
        return [
            'name' => $group->name,
            'slug' => $group->slug,
            'group_type' => $group->group_type,
            'profile_picture_url' => $group->profile_picture_url,
            'banner_image_url' => $group->banner_image_url,
            'datacenter' => $group->datacenter,
            'region' => $group->inferredRegion(),
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'recruiting_status' => $group->recruiting_status,
            'primary_focuses' => $group->primary_focuses ?? [],
            'experience_expectation' => $group->experience_expectation,
            'voice_expectation' => $group->voice_expectation,
            'preferred_languages' => $group->preferred_languages ?? [],
            'tags' => $group->tags ?? [],
            'active_timezone' => $group->active_timezone,
            'active_days' => $group->active_days ?? [],
            'active_start_time' => $group->active_start_time,
            'active_end_time' => $group->active_end_time,
        ];
    }
}
