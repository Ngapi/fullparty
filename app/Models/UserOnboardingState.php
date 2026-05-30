<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserOnboardingState extends Model
{
    public const STEP_WELCOME = 'welcome';

    public const STEP_DISCORD = 'discord';

    public const STEP_DISCORD_WARNING = 'discord_warning';

    public const STEP_NOTIFICATIONS = 'notifications';

    public const STEP_NEXT = 'next';

    public const STEPS = [
        self::STEP_WELCOME,
        self::STEP_DISCORD,
        self::STEP_DISCORD_WARNING,
        self::STEP_NOTIFICATIONS,
        self::STEP_NEXT,
    ];

    protected $fillable = [
        'user_id',
        'current_step',
        'discord_skipped_at',
        'notification_preferences_completed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'discord_skipped_at' => 'datetime',
            'notification_preferences_completed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSharedPayload(): array
    {
        return [
            'required' => $this->completed_at === null,
            'current_step' => $this->current_step,
            'discord_skipped_at' => $this->discord_skipped_at?->toIso8601String(),
            'notification_preferences_completed_at' => $this->notification_preferences_completed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
