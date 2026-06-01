<?php

namespace App\Services\Notifications;

use App\Models\GroupNotificationPreference;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Support\Notifications\NotificationCategory;
use App\Support\Notifications\NotificationPreferenceChannel;
use App\Support\Notifications\NotificationTopic;
use Illuminate\Validation\ValidationException;

class NotificationPreferenceSettingsService
{
    /**
     * @return array<string, array<string, bool>>
     */
    public function serializeUserPreferences(User $user): array
    {
        $storedPreferences = $user->notificationPreferences()
            ->get(['topic', 'channel', 'enabled'])
            ->mapWithKeys(fn (UserNotificationPreference $preference) => [
                $this->preferenceKey($preference->topic, $preference->channel) => (bool) $preference->enabled,
            ]);

        return collect(NotificationTopic::VALUES)
            ->mapWithKeys(function (string $topic) use ($user, $storedPreferences): array {
                $channels = collect(NotificationTopic::supportedChannels($topic))
                    ->mapWithKeys(function (string $channel) use ($user, $storedPreferences, $topic): array {
                        $key = $this->preferenceKey($topic, $channel);

                        return [
                            $channel => $storedPreferences->has($key)
                                ? (bool) $storedPreferences->get($key)
                                : $this->legacyDefault($user, $topic, $channel),
                        ];
                    })
                    ->all();

                return [$topic => $channels];
            })
            ->all();
    }

    /**
     * @return array<string, array<string, bool|null>>
     */
    public function serializeGroupPreferences(User $user, int $groupId): array
    {
        $storedPreferences = $user->groupNotificationPreferences()
            ->where('group_id', $groupId)
            ->get(['topic', 'channel', 'enabled'])
            ->mapWithKeys(fn (GroupNotificationPreference $preference) => [
                $this->preferenceKey($preference->topic, $preference->channel) => (bool) $preference->enabled,
            ]);

        return collect(NotificationTopic::GROUP_OVERRIDE_VALUES)
            ->mapWithKeys(function (string $topic) use ($storedPreferences): array {
                $channels = collect(NotificationTopic::supportedChannels($topic))
                    ->mapWithKeys(function (string $channel) use ($storedPreferences, $topic): array {
                        $key = $this->preferenceKey($topic, $channel);

                        return [$channel => $storedPreferences->has($key) ? (bool) $storedPreferences->get($key) : null];
                    })
                    ->all();

                return [$topic => $channels];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistUserPreferences(User $user, array $payload): void
    {
        $preferences = $this->validatePreferencePayload($payload, NotificationTopic::VALUES, allowNull: false);

        foreach ($preferences as $topic => $channels) {
            foreach ($channels as $channel => $enabled) {
                UserNotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'topic' => $topic,
                        'channel' => $channel,
                    ],
                    [
                        'enabled' => $enabled,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function persistGroupPreferences(User $user, int $groupId, array $payload): void
    {
        $preferences = $this->validatePreferencePayload($payload, NotificationTopic::GROUP_OVERRIDE_VALUES, allowNull: true);

        foreach ($preferences as $topic => $channels) {
            foreach ($channels as $channel => $enabled) {
                if ($enabled === null) {
                    GroupNotificationPreference::query()
                        ->where('user_id', $user->id)
                        ->where('group_id', $groupId)
                        ->where('topic', $topic)
                        ->where('channel', $channel)
                        ->delete();

                    continue;
                }

                GroupNotificationPreference::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'group_id' => $groupId,
                        'topic' => $topic,
                        'channel' => $channel,
                    ],
                    [
                        'enabled' => $enabled,
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $allowedTopics
     * @return array<string, array<string, bool|null>>
     */
    private function validatePreferencePayload(array $payload, array $allowedTopics, bool $allowNull): array
    {
        $validated = [];

        foreach ($payload as $topic => $channels) {
            if (! is_string($topic) || ! in_array($topic, $allowedTopics, true)) {
                throw ValidationException::withMessages([
                    'notification_preferences' => __('validation.in'),
                ]);
            }

            if (! is_array($channels)) {
                throw ValidationException::withMessages([
                    "notification_preferences.{$topic}" => __('validation.array'),
                ]);
            }

            foreach ($channels as $channel => $enabled) {
                if (
                    ! is_string($channel)
                    || ! in_array($channel, NotificationPreferenceChannel::VALUES, true)
                    || ! NotificationTopic::supportsChannel($topic, $channel)
                ) {
                    throw ValidationException::withMessages([
                        "notification_preferences.{$topic}.{$channel}" => __('validation.in'),
                    ]);
                }

                if ($enabled === null && $allowNull) {
                    $validated[$topic][$channel] = null;

                    continue;
                }

                if (! is_bool($enabled)) {
                    throw ValidationException::withMessages([
                        "notification_preferences.{$topic}.{$channel}" => __('validation.boolean'),
                    ]);
                }

                $validated[$topic][$channel] = $enabled;
            }
        }

        return $validated;
    }

    private function legacyDefault(User $user, string $topic, string $channel): bool
    {
        $category = NotificationTopic::categoryForTopic($topic);
        $categoryEnabled = (bool) $user->{NotificationCategory::preferenceField($category)};

        return match ($channel) {
            NotificationPreferenceChannel::IN_APP => $categoryEnabled,
            NotificationPreferenceChannel::EMAIL => $categoryEnabled && (bool) $user->email_notifications,
            NotificationPreferenceChannel::DISCORD => $categoryEnabled && (
                (bool) $user->discord_notifications
                || (NotificationTopic::defaultsToDiscordEnabled($topic) && $this->hasDiscordIntegration($user))
            ),
        };
    }

    private function hasDiscordIntegration(User $user): bool
    {
        if ($user->relationLoaded('discordUserIntegration')) {
            return $user->discordUserIntegration !== null;
        }

        return $user->discordUserIntegration()->exists();
    }

    private function preferenceKey(string $topic, string $channel): string
    {
        return "{$topic}:{$channel}";
    }
}
