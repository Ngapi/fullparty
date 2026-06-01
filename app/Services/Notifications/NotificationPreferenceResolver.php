<?php

namespace App\Services\Notifications;

use App\Models\GroupMembership;
use App\Models\GroupNotificationPreference;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\Notifications\NotificationCategory;
use App\Support\Notifications\NotificationPreferenceChannel;
use App\Support\Notifications\NotificationTopic;

class NotificationPreferenceResolver
{
    /** @var array<int, array<string, bool>> */
    private array $userPreferences = [];

    /** @var array<string, bool> */
    private array $groupPreferences = [];

    /** @var array<string, bool> */
    private array $groupMembershipNotifications = [];

    public function wants(User $user, NotificationEvent $event, string $channel): bool
    {
        NotificationPreferenceChannel::ensureValid($channel);

        $topic = $this->eventTopic($event);

        if (! NotificationTopic::supportsChannel($topic, $channel)) {
            return false;
        }

        if ($event->is_mandatory) {
            return $channel === NotificationPreferenceChannel::IN_APP
                || $this->userChannelEnabled($user, $channel);
        }

        if ($this->shouldUseGroupOverride($event, $topic)) {
            $membershipEnabled = $this->groupMembershipNotificationsEnabled(
                userId: (int) $user->id,
                groupId: (int) $event->group_id,
            );

            if (! $membershipEnabled) {
                return false;
            }

            $groupPreference = $this->groupPreference(
                userId: (int) $user->id,
                groupId: (int) $event->group_id,
                topic: $topic,
                channel: $channel,
            );

            if ($groupPreference !== null) {
                return $groupPreference;
            }
        }

        $userPreference = $this->userPreference($user, $topic, $channel);

        if ($userPreference !== null) {
            return $userPreference;
        }

        return $this->legacyDefault($user, $event, $channel);
    }

    public function eventTopic(NotificationEvent $event): string
    {
        $topic = $event->topic ?: NotificationTopic::forType($event->type, $event->category);

        NotificationTopic::ensureValid($topic);

        return $topic;
    }

    private function shouldUseGroupOverride(NotificationEvent $event, string $topic): bool
    {
        return $event->group_id !== null && NotificationTopic::isGroupOverrideTopic($topic);
    }

    private function userPreference(User $user, string $topic, string $channel): ?bool
    {
        $preferences = $this->userPreferences[(int) $user->id] ??= UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->get(['topic', 'channel', 'enabled'])
            ->mapWithKeys(fn (UserNotificationPreference $preference) => [
                $this->preferenceKey($preference->topic, $preference->channel) => (bool) $preference->enabled,
            ])
            ->all();

        return $preferences[$this->preferenceKey($topic, $channel)] ?? null;
    }

    private function groupPreference(int $userId, int $groupId, string $topic, string $channel): ?bool
    {
        $key = sprintf('%d:%d:%s', $userId, $groupId, $this->preferenceKey($topic, $channel));

        if (array_key_exists($key, $this->groupPreferences)) {
            return $this->groupPreferences[$key];
        }

        $preference = GroupNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->where('topic', $topic)
            ->where('channel', $channel)
            ->first(['enabled']);

        if (! $preference) {
            return null;
        }

        return $this->groupPreferences[$key] = (bool) $preference->enabled;
    }

    private function groupMembershipNotificationsEnabled(int $userId, int $groupId): bool
    {
        $key = "{$userId}:{$groupId}";

        if (array_key_exists($key, $this->groupMembershipNotifications)) {
            return $this->groupMembershipNotifications[$key];
        }

        $enabled = GroupMembership::query()
            ->where('user_id', $userId)
            ->where('group_id', $groupId)
            ->value('notifications_enabled');

        return $this->groupMembershipNotifications[$key] = $enabled === null ? true : (bool) $enabled;
    }

    private function legacyDefault(User $user, NotificationEvent $event, string $channel): bool
    {
        if ($channel === NotificationPreferenceChannel::IN_APP) {
            return (bool) $user->{NotificationCategory::preferenceField($event->category)};
        }

        if (
            $channel === NotificationPreferenceChannel::DISCORD
            && NotificationTopic::defaultsToDiscordEnabled($this->eventTopic($event))
            && $this->hasDiscordIntegration($user)
        ) {
            return (bool) $user->{NotificationCategory::preferenceField($event->category)};
        }

        return $this->userChannelEnabled($user, $channel)
            && (bool) $user->{NotificationCategory::preferenceField($event->category)};
    }

    private function hasDiscordIntegration(User $user): bool
    {
        if ($user->relationLoaded('discordUserIntegration')) {
            return $user->discordUserIntegration !== null;
        }

        return $user->discordUserIntegration()->exists();
    }

    private function userChannelEnabled(User $user, string $channel): bool
    {
        return match ($channel) {
            NotificationPreferenceChannel::EMAIL => (bool) $user->email_notifications,
            NotificationPreferenceChannel::DISCORD => (bool) $user->discord_notifications,
            NotificationPreferenceChannel::IN_APP => true,
        };
    }

    private function preferenceKey(string $topic, string $channel): string
    {
        return "{$topic}:{$channel}";
    }
}
