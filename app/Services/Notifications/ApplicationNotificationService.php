<?php

namespace App\Services\Notifications;

use App\Models\Activity;
use App\Models\ActivityApplication;
use App\Models\Group;
use App\Models\User;
use App\Support\Activities\ActivityDisplayName;
use App\Support\Notifications\NotificationCategory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ApplicationNotificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function notifySubmitted(ActivityApplication $application, mixed $actor): void
    {
        $recipients = $this->hostRecipients($application);

        if ($recipients->isNotEmpty()) {
            $event = $this->notificationService->createEvent(
                type: 'applications.new_for_review',
                category: NotificationCategory::APPLICATIONS,
                titleKey: 'notifications.applications.new_for_review.title',
                bodyKey: 'notifications.applications.new_for_review.body',
                messageParams: $this->messageParams($application),
                actionUrl: $this->moderatorActionUrl($application),
                actor: $actor instanceof User ? $actor : null,
                subject: $application->activity,
                payload: $this->payload($application),
            );

            $this->notificationService->sendAggregatedInAppNotifications(
                $event,
                $recipients,
                $this->submittedAggregateKey($application),
            );
        }

        $this->notifyApplicantSubmitted($application, $actor);
    }

    private function notifyApplicantSubmitted(ActivityApplication $application, mixed $actor): void
    {
        $recipient = $this->applicantRecipient($application);

        if (! $recipient) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.submitted',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.submitted.title',
            bodyKey: 'notifications.applications.submitted.body',
            messageParams: $this->messageParams($application),
            actionUrl: route('account.applications'),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipient);
        $this->notificationService->sendOffSiteNotifications($event, $recipient);
    }

    public function notifyUpdated(ActivityApplication $application, mixed $actor): void
    {
        $recipients = $this->hostRecipients($application);

        if ($recipients->isEmpty()) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.updated',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.updated.title',
            bodyKey: 'notifications.applications.updated.body',
            messageParams: $this->messageParams($application),
            actionUrl: $this->moderatorActionUrl($application),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipients);
        $this->notificationService->sendOffSiteNotifications($event, $recipients);
    }

    public function notifyWithdrawn(ActivityApplication $application, mixed $actor): void
    {
        $this->notifyHostWithdrawn($application, $actor);
        $this->notifyApplicantWithdrawn($application, $actor);
    }

    private function notifyHostWithdrawn(ActivityApplication $application, mixed $actor): void
    {
        $recipients = $this->hostRecipients($application);

        if ($recipients->isEmpty()) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.withdrawn',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.withdrawn.title',
            bodyKey: 'notifications.applications.withdrawn.body',
            messageParams: $this->messageParams($application),
            actionUrl: $this->moderatorActionUrl($application),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipients);
        $this->notificationService->sendOffSiteNotifications($event, $recipients);
    }

    private function notifyApplicantWithdrawn(ActivityApplication $application, mixed $actor): void
    {
        $recipient = $this->applicantRecipient($application);

        if (! $recipient) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.withdrawn',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.withdrawn.title',
            bodyKey: 'notifications.applications.withdrawn.body',
            messageParams: $this->messageParams($application),
            actionUrl: route('account.applications'),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipient);
        $this->notificationService->sendOffSiteNotifications($event, $recipient);
    }

    public function notifyDeclined(ActivityApplication $application, mixed $actor): void
    {
        $recipient = $this->applicantRecipient($application);

        if (! $recipient) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.declined',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.declined.title',
            bodyKey: filled($application->review_reason)
                ? 'notifications.applications.declined.body_with_reason'
                : 'notifications.applications.declined.body',
            messageParams: $this->messageParams($application),
            actionUrl: route('account.applications'),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipient);
        $this->notificationService->sendOffSiteNotifications($event, $recipient);
    }

    public function notifyCancelled(ActivityApplication $application, mixed $actor): void
    {
        $recipient = $this->applicantRecipient($application);

        if (! $recipient) {
            return;
        }

        $event = $this->notificationService->createEvent(
            type: 'applications.cancelled',
            category: NotificationCategory::APPLICATIONS,
            titleKey: 'notifications.applications.cancelled.title',
            bodyKey: 'notifications.applications.cancelled.body',
            messageParams: $this->messageParams($application),
            actionUrl: route('account.applications'),
            actor: $actor instanceof User ? $actor : null,
            subject: $application,
            payload: $this->payload($application),
        );

        $this->notificationService->sendInAppNotifications($event, $recipient);
        $this->notificationService->sendOffSiteNotifications($event, $recipient);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function hostRecipients(ActivityApplication $application): EloquentCollection
    {
        $activity = $application->activity;
        $hostId = $activity?->organized_by_user_id;

        if (! $hostId) {
            return new EloquentCollection;
        }

        return User::query()
            ->whereKey($hostId)
            ->where('application_notifications', true)
            ->when(
                $application->user_id !== null,
                fn ($query) => $query->whereKeyNot($application->user_id),
            )
            ->get();
    }

    private function applicantRecipient(ActivityApplication $application): ?User
    {
        $application->loadMissing('user');

        $recipient = $application->user;

        if (! $recipient instanceof User || ! $recipient->application_notifications) {
            return null;
        }

        return $recipient;
    }

    /**
     * @return array<string, mixed>
     */
    private function messageParams(ActivityApplication $application): array
    {
        return [
            'activity' => $this->activityTitle($application->activity),
            'group' => $application->activity?->group?->name,
            'character' => $this->characterName($application),
            'reason' => $application->review_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ActivityApplication $application): array
    {
        return [
            'application_id' => $application->id,
            'activity_id' => $application->activity?->id,
            'group_id' => $application->activity?->group?->id,
            'group_slug' => $application->activity?->group?->slug,
            'activity_title' => $this->activityTitle($application->activity),
            'character_name' => $this->characterName($application),
            'status' => $application->status,
            'review_reason' => $application->review_reason,
        ];
    }

    private function moderatorActionUrl(ActivityApplication $application): ?string
    {
        $group = $application->activity?->group;
        $activity = $application->activity;

        if (! $group instanceof Group || ! $activity instanceof Activity) {
            return null;
        }

        return route('groups.dashboard.activities.show', [
            'group' => $group,
            'activity' => $activity,
        ]);
    }

    private function activityTitle(?Activity $activity): string
    {
        return ActivityDisplayName::for($activity);
    }

    private function characterName(ActivityApplication $application): string
    {
        $application->loadMissing('selectedCharacter');

        return $application->selectedCharacter?->name
            ?? $application->applicant_character_name
            ?? 'Applicant';
    }

    private function submittedAggregateKey(ActivityApplication $application): string
    {
        return sprintf(
            'applications.new_for_review.activity.%d',
            (int) ($application->activity?->id ?? 0),
        );
    }
}
