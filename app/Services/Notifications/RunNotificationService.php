<?php

namespace App\Services\Notifications;

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\ActivitySlot;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Support\Activities\ActivityDisplayName;
use App\Support\Notifications\NotificationCategory;
use App\Support\Notifications\NotificationChannel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RunNotificationService
{
    private const STARTING_SOON_FLAG = 'run_notification_starting_soon_sent_at';

    private const STARTING_NOW_FLAG = 'run_notification_starting_now_sent_at';

    private const STARTING_SOON_MINUTES = 60;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * @param  Collection<int, ActivityApplication>|null  $applications
     */
    public function notifyCancelled(
        Activity $activity,
        mixed $actor,
        ?Collection $applications = null,
        ?string $reason = null,
    ): void {
        $recipients = $applications instanceof Collection
            ? $this->mergeRecipients(
                $this->recipientsFromApplications($applications),
                $this->assignedSlotRecipients($activity),
            )
            : $this->activeRunRecipients($activity);

        $this->sendRunNotification(
            activity: $activity,
            recipients: $recipients,
            type: 'runs.cancelled',
            titleKey: 'notifications.runs.cancelled.title',
            bodyKey: filled($reason)
                ? 'notifications.runs.cancelled.body_with_reason'
                : 'notifications.runs.cancelled.body',
            actor: $actor,
            messageParams: filled($reason) ? ['reason' => $reason] : [],
            payload: filled($reason) ? ['reason' => $reason] : [],
        );
    }

    public function notifyCompleted(Activity $activity, mixed $actor): void
    {
        $this->sendRunNotification(
            activity: $activity,
            recipients: $this->placedRunRecipients($activity),
            type: 'runs.completed',
            titleKey: 'notifications.runs.completed.title',
            bodyKey: 'notifications.runs.completed.body',
            actor: $actor,
            payload: [
                'completion' => $this->completionPayload($activity),
            ],
        );
    }

    /**
     * @return array{starting_soon: int, starting_now: int}
     */
    public function dispatchDueReminders(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $soonCutoff = $now->addMinutes(self::STARTING_SOON_MINUTES);

        $startingSoonCount = 0;

        Activity::query()
            ->with(['group', 'applications.user', 'applications.selectedCharacter'])
            ->whereIn('status', [
                Activity::STATUS_ASSIGNED,
                Activity::STATUS_UPCOMING,
                Activity::STATUS_ONGOING,
            ])
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now)
            ->where('starts_at', '<=', $soonCutoff)
            ->orderBy('starts_at')
            ->get()
            ->each(function (Activity $activity) use ($now, &$startingSoonCount): void {
                if (! $this->claimReminder($activity, self::STARTING_SOON_FLAG, $now)) {
                    return;
                }

                $this->notifyStartingSoon($activity);
                $startingSoonCount++;
            });

        $startingNowCount = 0;

        Activity::query()
            ->with(['group', 'applications.user', 'applications.selectedCharacter'])
            ->whereIn('status', [
                Activity::STATUS_ASSIGNED,
                Activity::STATUS_UPCOMING,
                Activity::STATUS_ONGOING,
            ])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $now)
            ->orderBy('starts_at')
            ->get()
            ->each(function (Activity $activity) use ($now, &$startingNowCount): void {
                if (! $this->claimReminder($activity, self::STARTING_NOW_FLAG, $now)) {
                    return;
                }

                $this->notifyStartingNow($activity);
                $startingNowCount++;
            });

        return [
            'starting_soon' => $startingSoonCount,
            'starting_now' => $startingNowCount,
        ];
    }

    private function notifyStartingSoon(Activity $activity): void
    {
        $this->sendRunNotification(
            activity: $activity,
            recipients: $this->placedRunRecipients($activity),
            type: 'runs.starting_soon',
            titleKey: 'notifications.runs.starting_soon.title',
            bodyKey: 'notifications.runs.starting_soon.body',
        );
    }

    private function notifyStartingNow(Activity $activity): void
    {
        $this->sendRunNotification(
            activity: $activity,
            recipients: $this->placedRunRecipients($activity),
            type: 'runs.starting_now',
            titleKey: 'notifications.runs.starting_now.title',
            bodyKey: 'notifications.runs.starting_now.body',
        );
    }

    private function createEvent(
        Activity $activity,
        string $type,
        string $titleKey,
        string $bodyKey,
        mixed $actor = null,
        array $messageParams = [],
        array $payload = [],
    ): NotificationEvent {
        $activity->loadMissing('group');

        return $this->notificationService->createEvent(
            type: $type,
            category: NotificationCategory::RUNS_AND_REMINDERS,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            messageParams: array_merge([
                'activity' => $this->activityTitle($activity),
                'group' => $activity->group?->name,
            ], $messageParams),
            actionUrl: $this->activityOverviewUrl($activity),
            actor: $actor instanceof User ? $actor : null,
            subject: $activity,
            payload: array_merge([
                'activity_id' => $activity->id,
                'group_id' => $activity->group?->id,
                'group_slug' => $activity->group?->slug,
                'activity_title' => $this->activityTitle($activity),
                'status' => $activity->status,
                'starts_at' => $activity->starts_at?->toIso8601String(),
            ], $payload),
        );
    }

    /**
     * @param  EloquentCollection<int, User>  $recipients
     */
    private function sendRunNotification(
        Activity $activity,
        EloquentCollection $recipients,
        string $type,
        string $titleKey,
        string $bodyKey,
        mixed $actor = null,
        array $messageParams = [],
        array $payload = [],
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        $event = $this->createEvent(
            activity: $activity,
            type: $type,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            actor: $actor,
            messageParams: $messageParams,
            payload: $payload,
        );

        $this->notificationService->sendInAppNotifications($event, $recipients);
        $this->notificationService->sendOffSiteNotifications($event, $recipients, [
            NotificationChannel::EMAIL,
            NotificationChannel::DISCORD,
        ]);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function activeApplicantRecipients(Activity $activity): EloquentCollection
    {
        return $this->applicantRecipientsForStatuses($activity, [
            ActivityApplication::STATUS_PENDING,
            ActivityApplication::STATUS_APPROVED,
            ActivityApplication::STATUS_ON_BENCH,
        ]);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function activeRunRecipients(Activity $activity): EloquentCollection
    {
        return $this->mergeRecipients(
            $this->activeApplicantRecipients($activity),
            $this->assignedSlotRecipients($activity),
        );
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function placedApplicantRecipients(Activity $activity): EloquentCollection
    {
        return $this->applicantRecipientsForStatuses($activity, [
            ActivityApplication::STATUS_APPROVED,
            ActivityApplication::STATUS_ON_BENCH,
        ]);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function placedRunRecipients(Activity $activity): EloquentCollection
    {
        return $this->mergeRecipients(
            $this->placedApplicantRecipients($activity),
            $this->assignedSlotRecipients($activity),
        );
    }

    /**
     * @param  Collection<int, ActivityApplication>  $applications
     * @return EloquentCollection<int, User>
     */
    private function recipientsFromApplications(Collection $applications): EloquentCollection
    {
        return new EloquentCollection(
            $applications
                ->map(function (ActivityApplication $application) {
                    $application->loadMissing('user');

                    return $application->user;
                })
                ->filter(fn ($user) => $user instanceof User && $user->run_and_reminder_notifications)
                ->unique('id')
                ->values()
                ->all()
        );
    }

    /**
     * @param  array<int, string>  $statuses
     * @return EloquentCollection<int, User>
     */
    private function applicantRecipientsForStatuses(Activity $activity, array $statuses): EloquentCollection
    {
        $activity->loadMissing('applications.user');

        return new EloquentCollection(
            $activity->applications
                ->filter(fn (ActivityApplication $application) => in_array($application->status, $statuses, true))
                ->map(fn (ActivityApplication $application) => $application->user)
                ->filter(fn ($user) => $user instanceof User && $user->run_and_reminder_notifications)
                ->unique('id')
                ->values()
                ->all()
        );
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function assignedSlotRecipients(Activity $activity): EloquentCollection
    {
        $activity->loadMissing('slots.assignedCharacter.user');

        return new EloquentCollection(
            $activity->slots
                ->map(fn (ActivitySlot $slot) => $slot->assignedCharacter?->user)
                ->filter(fn ($user) => $user instanceof User && $user->run_and_reminder_notifications)
                ->unique('id')
                ->values()
                ->all()
        );
    }

    /**
     * @param  EloquentCollection<int, User>  ...$collections
     * @return EloquentCollection<int, User>
     */
    private function mergeRecipients(EloquentCollection ...$collections): EloquentCollection
    {
        return new EloquentCollection(
            collect($collections)
                ->flatMap(fn (EloquentCollection $collection) => $collection->all())
                ->filter(fn ($user) => $user instanceof User && $user->run_and_reminder_notifications)
                ->unique('id')
                ->values()
                ->all()
        );
    }

    private function activityOverviewUrl(Activity $activity): string
    {
        $activity->loadMissing('group');

        if (! $activity->group) {
            return route('account.applications');
        }

        $parameters = [
            'group' => $activity->group->slug,
            'activity' => $activity->id,
        ];

        if (filled($activity->secret_key)) {
            $parameters['secretKey'] = $activity->secret_key;
        }

        return route('groups.activities.overview', $parameters);
    }

    /**
     * @return array<string, mixed>
     */
    private function completionPayload(Activity $activity): array
    {
        $activity->loadMissing(['activityTypeVersion', 'progressMilestones']);

        return [
            'completed_at' => $activity->completed_at?->toIso8601String(),
            'progress_recorded_at' => $activity->progress_recorded_at?->toIso8601String(),
            'progress_recorded_by_user_id' => $activity->progress_recorded_by_user_id,
            'progress_entry_mode' => $activity->progress_entry_mode,
            'progress_link_url' => $activity->progress_link_url,
            'progress_notes' => $activity->progress_notes,
            'furthest_progress_key' => $activity->furthest_progress_key,
            'furthest_progress_label' => $this->progressPointLabel($activity, $activity->furthest_progress_key),
            'furthest_progress_percent' => $activity->furthest_progress_percent !== null
                ? (float) $activity->furthest_progress_percent
                : null,
            'milestones' => $activity->progressMilestones
                ->map(fn ($milestone) => [
                    'milestone_key' => $milestone->milestone_key,
                    'milestone_label' => $milestone->milestone_label,
                    'kills' => (int) $milestone->kills,
                    'best_progress_percent' => $milestone->best_progress_percent !== null
                        ? (float) $milestone->best_progress_percent
                        : null,
                    'source' => $milestone->source,
                    'notes' => $milestone->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    private function progressPointLabel(Activity $activity, ?string $key): ?array
    {
        if (blank($key)) {
            return null;
        }

        $milestoneLabel = $activity->progressMilestones
            ->firstWhere('milestone_key', $key)
            ?->milestone_label;

        if (is_array($milestoneLabel)) {
            return $milestoneLabel;
        }

        $progPoint = collect($activity->activityTypeVersion?->prog_points ?? [])
            ->firstWhere('key', $key);
        $progPointLabel = is_array($progPoint) ? ($progPoint['label'] ?? null) : null;

        return is_array($progPointLabel) ? $progPointLabel : null;
    }

    private function activityTitle(Activity $activity): string
    {
        return ActivityDisplayName::for($activity);
    }

    private function reminderAlreadySent(Activity $activity, string $key): bool
    {
        return filled(($activity->settings ?? [])[$key] ?? null);
    }

    private function claimReminder(Activity $activity, string $key, CarbonImmutable $timestamp): bool
    {
        return DB::transaction(function () use ($activity, $key, $timestamp): bool {
            $lockedActivity = Activity::query()
                ->whereKey($activity->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedActivity || $this->reminderAlreadySent($lockedActivity, $key)) {
                return false;
            }

            $settings = $lockedActivity->settings ?? [];
            $settings[$key] = $timestamp->toIso8601String();

            $lockedActivity->forceFill([
                'settings' => $settings,
            ])->save();

            $activity->forceFill([
                'settings' => $settings,
            ]);

            return true;
        });
    }
}
