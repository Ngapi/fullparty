<?php

namespace App\Support\Notifications;

use InvalidArgumentException;

class NotificationPreferenceChannel
{
    public const IN_APP = 'in_app';

    public const EMAIL = NotificationChannel::EMAIL;

    public const DISCORD = NotificationChannel::DISCORD;

    public const VALUES = [
        self::IN_APP,
        self::EMAIL,
        self::DISCORD,
    ];

    public const OFF_SITE_VALUES = [
        self::EMAIL,
        self::DISCORD,
    ];

    public static function ensureValid(string $channel): void
    {
        if (! in_array($channel, self::VALUES, true)) {
            throw new InvalidArgumentException("Invalid notification preference channel [{$channel}] supplied.");
        }
    }
}
