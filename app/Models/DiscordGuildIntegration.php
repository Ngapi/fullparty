<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscordGuildIntegration extends Model
{
    protected $fillable = [
        'group_id',
        'installed_by_user_id',
        'discord_guild_id',
        'installed_by_discord_user_id',
        'name',
        'icon_url',
        'permissions',
        'guild_installed_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'guild_installed_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function installedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by_user_id');
    }
}
