<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordUserIntegration extends Model
{
    protected $fillable = [
        'user_id',
        'discord_user_id',
        'username',
        'global_name',
        'avatar_url',
        'user_app_installed_at',
        'last_seen_interaction_at',
        'last_delivery_failed_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'user_app_installed_at' => 'datetime',
            'last_seen_interaction_at' => 'datetime',
            'last_delivery_failed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
