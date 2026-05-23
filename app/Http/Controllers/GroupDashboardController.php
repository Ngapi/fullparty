<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Group;
use App\Models\GroupMembership;
use Inertia\Inertia;
use Inertia\Response;

class GroupDashboardController extends Controller
{
    public function show(Group $group): Response
    {
        $group->load([
            'owner',
            'memberships.user',
            'activities' => fn ($query) => $query
                ->with([
                    'organizer',
                    'organizerCharacter',
                    'activityType',
                ])
                ->withCount([
                    'slots',
                    'applications',
                ]),
        ]);

        if (! $group->hasMember(auth()->id())) {
            abort(403);
        }

        $currentUserId = auth()->id();
        $currentMembership = $group->memberships->firstWhere('user_id', $currentUserId);
        $currentFollow = $group->followers()
            ->where('users.id', $currentUserId)
            ->first();
        $notificationsEnabled = $currentFollow
            ? (bool) $currentFollow->pivot->notifications_enabled
            : true;
        $canManageActivities = $group->hasModeratorAccess(auth()->id());
        $now = now();
        $activities = $group->activities
            ->when(
                ! $canManageActivities,
                fn ($activities) => $activities->reject(fn (Activity $activity) => Activity::isModeratorOnlyStatus($activity->status))
            )
            ->sortByDesc('updated_at')
            ->values();
        $upcomingActivities = $activities
            ->filter(fn (Activity $activity) => ! Activity::isArchivedStatus($activity->status)
                && $activity->starts_at !== null
                && $activity->starts_at->gte($now))
            ->sort(function (Activity $left, Activity $right) {
                $startsAtComparison = $left->starts_at->getTimestamp()
                    <=> $right->starts_at->getTimestamp();

                if ($startsAtComparison !== 0) {
                    return $startsAtComparison;
                }

                return $left->id <=> $right->id;
            })
            ->values();
        $historyActivities = $activities
            ->filter(fn (Activity $activity) => Activity::isArchivedStatus($activity->status))
            ->sortByDesc(fn (Activity $activity) => $this->activityHistoryTimestamp($activity))
            ->values();
        $statusCounts = collect(Activity::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => $activities->where('status', $status)->count()]);
        $memberRoleBreakdown = [
            GroupMembership::ROLE_OWNER => $group->memberships
                ->where('role', GroupMembership::ROLE_OWNER)
                ->count(),
            GroupMembership::ROLE_MODERATOR => $group->memberships
                ->where('role', GroupMembership::ROLE_MODERATOR)
                ->count(),
            GroupMembership::ROLE_MEMBER => $group->memberships
                ->where('role', GroupMembership::ROLE_MEMBER)
                ->count(),
        ];
        $lastActivityAt = $activities->first()?->updated_at?->toIso8601String();
        $latestMemberJoinAt = $group->memberships
            ->sortByDesc('joined_at')
            ->first()?->joined_at?->toIso8601String();

        return Inertia::render($this->dashboardComponent($group), [
            'group' => [
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
                'current_user_role' => $group->memberships
                    ->firstWhere('user_id', auth()->id())
                    ?->role,
                'follow' => [
                    'is_following' => true,
                    'notifications_enabled' => $notificationsEnabled,
                ],
                'permissions' => [
                    'can_manage_group' => $group->isOwnedBy(auth()->id()),
                    'can_manage_members' => $canManageActivities,
                    'can_manage_activities' => $canManageActivities,
                    'can_leave' => $currentMembership instanceof GroupMembership
                        && ! $group->isOwnedBy($currentUserId),
                    'can_toggle_notifications' => $currentMembership instanceof GroupMembership,
                ],
                'stats' => [
                    'member_count' => $group->memberships->count(),
                    'moderator_count' => $group->memberships
                        ->where('role', GroupMembership::ROLE_MODERATOR)
                        ->count(),
                    'activity_count' => $activities->count(),
                    'planned_count' => (int) $statusCounts->get(Activity::STATUS_PLANNED, 0),
                    'scheduled_count' => (int) $statusCounts->get(Activity::STATUS_SCHEDULED, 0),
                    'assigned_count' => (int) $statusCounts->get(Activity::STATUS_ASSIGNED, 0),
                    'upcoming_count' => (int) $statusCounts->get(Activity::STATUS_UPCOMING, 0),
                    'ongoing_count' => (int) $statusCounts->get(Activity::STATUS_ONGOING, 0),
                    'completed_count' => (int) $statusCounts->get(Activity::STATUS_COMPLETE, 0),
                    'cancelled_count' => (int) $statusCounts->get(Activity::STATUS_CANCELLED, 0),
                    'open_application_count' => $activities
                        ->filter(fn (Activity $activity) => $activity->acceptsApplications())
                        ->count(),
                    'guest_friendly_count' => $activities
                        ->where('allow_guest_applications', true)
                        ->count(),
                    'public_activity_count' => $activities
                        ->where('is_public', true)
                        ->count(),
                    'last_activity_at' => $lastActivityAt,
                    'latest_member_join_at' => $latestMemberJoinAt,
                ],
                'member_role_breakdown' => [
                    'owner' => $memberRoleBreakdown[GroupMembership::ROLE_OWNER],
                    'moderator' => $memberRoleBreakdown[GroupMembership::ROLE_MODERATOR],
                    'member' => $memberRoleBreakdown[GroupMembership::ROLE_MEMBER],
                ],
                'members_preview' => $group->memberships
                    ->sortBy(function (GroupMembership $membership) {
                        return array_search($membership->role, GroupMembership::ROLES, true);
                    })
                    ->take(6)
                    ->values()
                    ->map(fn (GroupMembership $membership) => [
                        'id' => $membership->user->id,
                        'name' => $membership->user->name,
                        'avatar_url' => $membership->user->avatar_url,
                        'role' => $membership->role,
                        'joined_at' => $membership->joined_at?->toIso8601String(),
                    ]),
                'activity_status_breakdown' => collect(Activity::STATUSES)
                    ->map(fn (string $status) => [
                        'status' => $status,
                        'count' => (int) $statusCounts->get($status, 0),
                    ])
                    ->values(),
                'upcoming_activities' => $upcomingActivities
                    ->take(8)
                    ->map(fn (Activity $activity) => $this->serializeDashboardActivity($activity, $group, $canManageActivities)),
                'history_activities' => $historyActivities
                    ->take(6)
                    ->map(fn (Activity $activity) => $this->serializeDashboardActivity($activity, $group, $canManageActivities)),
            ],
        ]);
    }

    private function dashboardComponent(Group $group): string
    {
        return $group->group_type === Group::TYPE_STATIC
            ? 'Dashboard/Groups/StaticDashboard'
            : 'Dashboard/Groups/CommunityDashboard';
    }

    private function activityHistoryTimestamp(Activity $activity): int
    {
        return $activity->completed_at?->getTimestamp()
            ?? $activity->starts_at?->getTimestamp()
            ?? $activity->updated_at?->getTimestamp()
            ?? $activity->created_at?->getTimestamp()
            ?? 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDashboardActivity(Activity $activity, Group $group, bool $canManageActivities): array
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
            'is_public' => $activity->is_public,
            'secret_key' => $canManageActivities ? $activity->secret_key : null,
            'can_view_overview' => $this->canViewActivityOverview($activity, $group, $canManageActivities),
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
            'slot_count' => (int) ($activity->slots_count ?? 0),
            'application_count' => (int) ($activity->applications_count ?? 0),
            'created_at' => $activity->created_at?->toIso8601String(),
            'updated_at' => $activity->updated_at?->toIso8601String(),
        ];
    }

    private function canViewActivityOverview(Activity $activity, Group $group, bool $canManageActivities): bool
    {
        if (Activity::isModeratorOnlyStatus($activity->status)) {
            return $canManageActivities;
        }

        if ($activity->is_public) {
            return $group->is_public || $group->hasMember(auth()->id());
        }

        return $canManageActivities && filled($activity->secret_key);
    }
}
