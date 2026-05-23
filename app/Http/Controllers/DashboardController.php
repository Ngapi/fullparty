<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\Group;
use App\Services\Groups\ActivityApplicationWithdrawalService;
use App\Services\Notifications\NotificationInboxService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NotificationInboxService $notificationInboxService,
        private readonly ActivityApplicationWithdrawalService $applicationWithdrawalService,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user()->load([
            'primaryCharacter',
            'characters',
            'socialAccounts',
        ]);

        $ownedGroups = $user->ownedGroups()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
        $moderatedGroups = $user->moderatedGroups()
            ->orderBy('groups.name')
            ->get(['groups.id', 'groups.name', 'groups.slug']);
        $memberGroups = $user->memberGroups()
            ->orderBy('groups.name')
            ->get(['groups.id', 'groups.name', 'groups.slug']);

        $applicationBaseQuery = ActivityApplication::query()
            ->with([
                'activity.group',
                'activity.activityTypeVersion',
                'selectedCharacter',
            ])
            ->where('user_id', $user->id);

        $activeApplications = (clone $applicationBaseQuery)
            ->whereIn('status', [
                ActivityApplication::STATUS_PENDING,
                ActivityApplication::STATUS_APPROVED,
                ActivityApplication::STATUS_ON_BENCH,
            ])
            ->whereHas('activity', fn ($query) => $query->whereNotIn('status', Activity::ARCHIVED_STATUSES))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        $upcomingParticipations = $activeApplications
            ->sortBy([
                fn (ActivityApplication $application) => $application->activity?->starts_at === null ? 1 : 0,
                fn (ActivityApplication $application) => $application->activity?->starts_at?->getTimestamp() ?? PHP_INT_MAX,
            ])
            ->values()
            ->take(6)
            ->map(fn (ActivityApplication $application) => $this->serializeApplication($application))
            ->all();

        $recentApplications = (clone $applicationBaseQuery)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (ActivityApplication $application) => $this->serializeApplication($application))
            ->all();

        $confirmedParticipationCount = $activeApplications
            ->whereIn('status', [
                ActivityApplication::STATUS_APPROVED,
                ActivityApplication::STATUS_ON_BENCH,
            ])
            ->count();

        $completedParticipationCount = ActivityApplication::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                ActivityApplication::STATUS_APPROVED,
                ActivityApplication::STATUS_ON_BENCH,
            ])
            ->whereHas('activity', fn ($query) => $query->where('status', Activity::STATUS_COMPLETE))
            ->count();

        return Inertia::render('Dashboard/Dashboard', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'primary_character' => $user->primaryCharacter ? [
                    'id' => $user->primaryCharacter->id,
                    'name' => $user->primaryCharacter->name,
                    'world' => $user->primaryCharacter->world,
                    'datacenter' => $user->primaryCharacter->datacenter,
                    'avatar_url' => $user->primaryCharacter->avatar_url,
                ] : null,
            ],
            'summary' => [
                'unread_notification_count' => $this->notificationInboxService->unreadCount($user),
                'verified_character_count' => $user->characters->count(),
                'connected_account_count' => $user->socialAccounts->count(),
                'group_count' => $ownedGroups->count() + $moderatedGroups->count() + $memberGroups->count(),
                'owned_group_count' => $ownedGroups->count(),
                'moderated_group_count' => $moderatedGroups->count(),
                'member_group_count' => $memberGroups->count(),
                'active_application_count' => $activeApplications->count(),
                'pending_application_count' => $activeApplications
                    ->where('status', ActivityApplication::STATUS_PENDING)
                    ->count(),
                'confirmed_participation_count' => $confirmedParticipationCount,
                'completed_participation_count' => $completedParticipationCount,
            ],
            'setup' => [
                'has_primary_character' => $user->primaryCharacter !== null,
                'has_verified_characters' => $user->characters->isNotEmpty(),
                'public_profile' => (bool) $user->public_profile,
                'public_characters' => (bool) $user->public_characters,
                'connected_providers' => $user->socialAccounts
                    ->pluck('provider')
                    ->values()
                    ->all(),
            ],
            'groups' => [
                'owned' => [
                    'count' => $ownedGroups->count(),
                    'items' => $this->serializeGroupLinks($ownedGroups),
                ],
                'moderated' => [
                    'count' => $moderatedGroups->count(),
                    'items' => $this->serializeGroupLinks($moderatedGroups),
                ],
                'member' => [
                    'count' => $memberGroups->count(),
                    'items' => $this->serializeGroupLinks($memberGroups),
                ],
            ],
            'upcomingParticipations' => $upcomingParticipations,
            'recentApplications' => $recentApplications,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplication(ActivityApplication $application): array
    {
        $activity = $application->activity;
        $character = $application->selectedCharacter;
        $canEdit = $this->applicationCanBeModified($application);
        $canWithdraw = $activity
            ? $this->applicationWithdrawalService->applicationCanBeWithdrawn($activity, $application)
            : false;
        $isRostered = $this->applicationWithdrawalService->applicationIsRostered($application);

        return [
            'id' => $application->id,
            'status' => $application->status,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'reviewed_at' => $application->reviewed_at?->toIso8601String(),
            'review_reason' => $application->review_reason,
            'notes' => $application->notes,
            'can_edit' => $canEdit,
            'can_withdraw' => $canWithdraw,
            'is_rostered' => $isRostered,
            'group' => [
                'name' => $activity?->group?->name,
                'slug' => $activity?->group?->slug,
            ],
            'activity' => [
                'id' => $activity?->id,
                'title' => $activity?->title,
                'description' => $activity?->description,
                'status' => $activity?->status,
                'starts_at' => $activity?->starts_at?->toIso8601String(),
                'duration_hours' => $activity?->duration_hours,
                'is_public' => (bool) ($activity?->is_public ?? false),
                'secret_key' => $activity?->secret_key,
                'type_name' => $activity?->activityTypeVersion?->name,
            ],
            'character' => [
                'name' => $character?->name ?? $application->applicant_character_name,
                'world' => $character?->world ?? $application->applicant_world,
                'datacenter' => $character?->datacenter ?? $application->applicant_datacenter,
                'avatar_url' => $character?->avatar_url ?? $application->applicant_avatar_url,
            ],
        ];
    }

    private function applicationCanBeModified(ActivityApplication $application): bool
    {
        $activity = $application->activity;

        if (! $activity) {
            return false;
        }

        return $application->status === ActivityApplication::STATUS_PENDING
            && $activity->needs_application
            && ! Activity::isArchivedStatus($activity->status)
            && ! $this->applicationWithdrawalService->applicationIsRostered($application);
    }

    /**
     * @param  Collection<int, Group>  $groups
     * @return array<int, array<string, string|int>>
     */
    private function serializeGroupLinks($groups): array
    {
        return $groups
            ->take(4)
            ->values()
            ->map(fn (Group $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'href' => route('groups.dashboard', $group, false),
            ])
            ->all();
    }
}
