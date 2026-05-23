<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\ScheduledRun;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ManagedImageStorage;
use App\Services\Notifications\GroupUpdateNotificationService;
use App\Support\Audit\AuditScope;
use App\Support\Audit\AuditSeverity;
use App\Support\Input\RequestTextInputSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    private const IMAGE_DIRECTORY = 'groups';

    public function __construct(
        private readonly ManagedImageStorage $managedImageStorage,
        private readonly AuditLogger $auditLogger,
        private readonly GroupUpdateNotificationService $groupUpdateNotificationService,
        private readonly RequestTextInputSanitizer $requestTextInputSanitizer,
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
            $this->discoverGroupsQuery($user->id)->paginate(
                perPage: 20,
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
        $this->requestTextInputSanitizer->sanitize($request, ['query']);

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $query = trim((string) ($validated['query'] ?? ''));

        if ($query === '') {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 10,
                    'total' => 0,
                ],
            ]);
        }

        $like = '%'.$query.'%';

        $paginator = Group::query()
            ->select('groups.*')
            ->selectRaw(
                'CASE
                    WHEN groups.owner_id = ? THEN 0
                    WHEN current_membership.role = ? THEN 1
                    WHEN current_membership.role = ? THEN 2
                    ELSE 3
                END as search_priority',
                [
                    $user->id,
                    GroupMembership::ROLE_MODERATOR,
                    GroupMembership::ROLE_MEMBER,
                ]
            )
            ->leftJoin('group_memberships as current_membership', function ($join) use ($user) {
                $join->on('current_membership.group_id', '=', 'groups.id')
                    ->where('current_membership.user_id', '=', $user->id);
            })
            ->with(['memberships', 'scheduledRuns'])
            ->where(function ($queryBuilder) use ($user) {
                $queryBuilder
                    ->where('groups.owner_id', $user->id)
                    ->orWhereNotNull('current_membership.user_id')
                    ->orWhere('groups.is_visible', true);
            })
            ->where(function ($queryBuilder) use ($like) {
                $queryBuilder
                    ->where('groups.name', 'like', $like)
                    ->orWhere('groups.description', 'like', $like)
                    ->orWhere('groups.slug', 'like', $like);
            })
            ->orderBy('search_priority')
            ->orderBy('groups.name')
            ->paginate(
                perPage: 10,
                page: (int) ($validated['page'] ?? 1)
            );

        return response()->json($this->serializePaginatedGroups($paginator, $user->id));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->sanitizeGroupInput($request);

        $validated = $request->validate(
            $this->storeRules(),
            $this->storeValidationMessages(),
        );
        $profilePictureUrl = $this->managedImageStorage->uploadImageIfPresent(
            $request->file('profile_picture'),
            self::IMAGE_DIRECTORY,
            true
        );

        $group = DB::transaction(function () use ($validated, $profilePictureUrl) {
            $group = Group::create([
                'owner_id' => auth()->id(),
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'profile_picture_url' => $profilePictureUrl,
                'discord_invite_url' => $validated['discord_invite_url'] ?? null,
                'datacenter' => $validated['datacenter'],
                'is_public' => $validated['is_public'],
                'is_visible' => $validated['is_visible'],
                'slug' => $validated['slug'],
                'group_type' => $validated['group_type'],
            ]);

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
            metadata: [
                'name' => $group->name,
                'slug' => $group->slug,
                'group_type' => $group->group_type,
                'datacenter' => $group->datacenter,
                'is_public' => $group->is_public,
                'is_visible' => $group->is_visible,
            ],
        );

        return redirect()->route('groups.show', $group)->with('success', 'group_created');
    }

    public function show(Group $group): Response
    {
        $group->load([
            'owner',
            'memberships.user',
            'activities.organizer',
            'activities.organizerCharacter',
            'activities.activityType',
            'activities.slots',
            'activities.applications',
        ]);

        $currentUserId = auth()->id();

        if (! $group->is_visible && ! $group->hasMember($currentUserId)) {
            abort(404);
        }

        return Inertia::render('Groups/Profile', [
            'group' => $this->serializeGroupProfile($group, $currentUserId),
        ]);
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

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    private function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'discord_invite_url' => ['nullable', 'url', 'max:500'],
            'datacenter' => ['required', 'string', Rule::in(config('datacenters.values', []))],
            'is_public' => ['required', 'boolean'],
            'is_visible' => ['required', 'boolean'],
            'group_type' => ['required', 'string', Rule::in(Group::TYPES)],
            'slug' => [
                'required',
                'string',
                'max:8',
                'regex:/^[a-z]{1,8}$/',
                Rule::notIn(['admin', 'api', 'auth', 'groups', 'group', 'invite', 'invites', 'login', 'register', 'settings']),
                Rule::unique('groups', 'slug'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function storeValidationMessages(): array
    {
        return [
            'profile_picture.mimes' => __('groups.index.create_modal.validation.image_invalid_format'),
        ];
    }

    private function sanitizeGroupInput(Request $request): void
    {
        $this->requestTextInputSanitizer->sanitize(
            $request,
            ['name'],
            ['description'],
        );
    }

    private function serializeGroupListItem(Group $group, int $currentUserId): array
    {
        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'profile_picture_url' => $group->profile_picture_url,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'slug' => $group->slug,
            'group_type' => $group->group_type,
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

    private function discoverGroupsQuery(int $currentUserId)
    {
        $latestRunActivity = ScheduledRun::query()
            ->selectRaw('group_id, MAX(updated_at) as latest_run_activity_at')
            ->groupBy('group_id');

        return Group::query()
            ->select('groups.*')
            ->leftJoinSub($latestRunActivity, 'latest_run_activity', function ($join) {
                $join->on('latest_run_activity.group_id', '=', 'groups.id');
            })
            ->with(['memberships', 'scheduledRuns'])
            ->visible()
            ->whereDoesntHave('memberships', function ($query) use ($currentUserId) {
                $query->where('user_id', $currentUserId);
            })
            ->orderByRaw(
                'CASE
                    WHEN latest_run_activity.latest_run_activity_at IS NOT NULL
                        AND latest_run_activity.latest_run_activity_at > groups.updated_at
                        THEN latest_run_activity.latest_run_activity_at
                    ELSE groups.updated_at
                END DESC'
            );
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

    private function serializeGroupProfile(Group $group, ?int $currentUserId): array
    {
        $visibleActivities = $this->visibleProfileActivities($group, $currentUserId);
        [$currentActivities, $recentActivities] = $this->partitionProfileActivities($visibleActivities);
        $currentMembership = $currentUserId
            ? $group->memberships->firstWhere('user_id', $currentUserId)
            : null;
        $currentFollow = $this->currentFollowForUser($group, $currentUserId);
        $isMember = $currentMembership instanceof GroupMembership;
        $isFollowing = $currentFollow instanceof User || $isMember;
        $notificationsEnabled = $currentFollow instanceof User
            ? (bool) $currentFollow->pivot->notifications_enabled
            : $isMember;
        $isBanned = $group->isBanned($currentUserId);

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'profile_picture_url' => $group->profile_picture_url,
            'discord_invite_url' => $group->discord_invite_url,
            'datacenter' => $group->datacenter,
            'is_public' => $group->is_public,
            'is_visible' => $group->is_visible,
            'slug' => $group->slug,
            'group_type' => $group->group_type,
            'owner' => [
                'id' => $group->owner?->id,
                'name' => $group->owner?->name,
                'avatar_url' => $group->owner?->avatar_url,
            ],
            'current_user_role' => $currentMembership?->role,
            'follow' => [
                'is_following' => $isFollowing,
                'notifications_enabled' => $notificationsEnabled,
            ],
            'permissions' => [
                'can_join' => $group->is_public
                    && $group->usesCommunityJoinFlow()
                    && ! $isMember
                    && ! $isBanned,
                'can_follow' => $group->is_public
                    && ! $isMember
                    && ! $isFollowing
                    && ! $isBanned,
                'can_unfollow' => $currentFollow instanceof User
                    && ! $isMember,
                'can_leave' => $isMember
                    && ! $group->isOwnedBy($currentUserId),
                'can_toggle_notifications' => $currentUserId !== null
                    && $isFollowing,
                'can_access_dashboard' => $isMember,
            ],
            'stats' => [
                'member_count' => $group->memberships->count(),
                'moderator_count' => $group->memberships
                    ->where('role', GroupMembership::ROLE_MODERATOR)
                    ->count(),
                'activity_count' => $visibleActivities->count(),
                'current_activity_count' => $visibleActivities
                    ->reject(fn (Activity $activity) => Activity::isArchivedStatus($activity->status))
                    ->count(),
                'completed_activity_count' => $visibleActivities
                    ->where('status', Activity::STATUS_COMPLETE)
                    ->count(),
            ],
            'staff_members' => $group->memberships
                ->filter(fn (GroupMembership $membership) => in_array($membership->role, [
                    GroupMembership::ROLE_OWNER,
                    GroupMembership::ROLE_MODERATOR,
                ], true))
                ->sortBy(function (GroupMembership $membership) {
                    return array_search($membership->role, GroupMembership::ROLES, true) ?: 0;
                })
                ->values()
                ->map(fn (GroupMembership $membership) => [
                    'id' => $membership->user->id,
                    'name' => $membership->user->name,
                    'avatar_url' => $membership->user->avatar_url,
                    'role' => $membership->role,
                    'joined_at' => $membership->joined_at?->toIso8601String(),
                ]),
            'activities' => [
                'current' => $currentActivities,
                'recent' => $recentActivities,
            ],
        ];
    }

    private function currentFollowForUser(Group $group, ?int $currentUserId): ?User
    {
        if ($currentUserId === null) {
            return null;
        }

        return $group->followers()
            ->where('users.id', $currentUserId)
            ->first();
    }

    private function visibleProfileActivities(Group $group, ?int $currentUserId): Collection
    {
        $canSeePublicActivities = $group->is_public || $group->hasMember($currentUserId);
        $canSeeModeratorOnlyActivities = $group->hasModeratorAccess($currentUserId);

        if (! $canSeePublicActivities) {
            return collect();
        }

        return $group->activities
            ->filter(function (Activity $activity) use ($canSeeModeratorOnlyActivities) {
                if (Activity::isModeratorOnlyStatus($activity->status)) {
                    return $canSeeModeratorOnlyActivities;
                }

                return $activity->is_public;
            })
            ->values();
    }

    private function partitionProfileActivities(Collection $activities): array
    {
        $currentActivities = $activities
            ->reject(fn (Activity $activity) => Activity::isArchivedStatus($activity->status))
            ->sort(function (Activity $left, Activity $right) {
                $startsAtComparison = ($left->starts_at?->getTimestamp() ?? PHP_INT_MAX)
                    <=> ($right->starts_at?->getTimestamp() ?? PHP_INT_MAX);

                if ($startsAtComparison !== 0) {
                    return $startsAtComparison;
                }

                return ($right->updated_at?->getTimestamp() ?? 0)
                    <=> ($left->updated_at?->getTimestamp() ?? 0);
            })
            ->take(6)
            ->values()
            ->map(fn (Activity $activity) => $this->serializeProfileActivity($activity))
            ->all();

        $recentActivities = $activities
            ->sortByDesc(fn (Activity $activity) => $activity->updated_at?->getTimestamp() ?? 0)
            ->take(6)
            ->values()
            ->map(fn (Activity $activity) => $this->serializeProfileActivity($activity))
            ->all();

        return [$currentActivities, $recentActivities];
    }

    private function serializeProfileActivity(Activity $activity): array
    {
        return [
            'id' => $activity->id,
            'activity_type' => [
                'id' => $activity->activityType?->id,
                'slug' => $activity->activityType?->slug,
                'draft_name' => $activity->activityType?->draft_name,
            ],
            'title' => $activity->title,
            'status' => $activity->status,
            'starts_at' => $activity->starts_at?->toIso8601String(),
            'duration_hours' => $activity->duration_hours,
            'needs_application' => $activity->needs_application,
            'allow_guest_applications' => $activity->allow_guest_applications,
            'organized_by' => $activity->organizer ? [
                'id' => $activity->organizer->id,
                'name' => $activity->organizer->name,
                'avatar_url' => $activity->organizer->avatar_url,
            ] : null,
            'organized_by_character' => $activity->organizerCharacter ? [
                'id' => $activity->organizerCharacter->id,
                'user_id' => $activity->organizerCharacter->user_id,
                'name' => $activity->organizerCharacter->name,
                'avatar_url' => $activity->organizerCharacter->avatar_url,
            ] : null,
            'slot_count' => $activity->slots->count(),
            'application_count' => $activity->applications->count(),
            'created_at' => $activity->created_at?->toIso8601String(),
            'updated_at' => $activity->updated_at?->toIso8601String(),
        ];
    }
}
