<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Group extends Model
{
    use HasFactory;

    public const TYPE_COMMUNITY = 'community';

    public const TYPE_STATIC = 'static';

    public const TYPES = [
        self::TYPE_COMMUNITY,
        self::TYPE_STATIC,
    ];

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'profile_picture_url',
        'banner_image_url',
        'discord_invite_url',
        'datacenter',
        'is_public',
        'is_visible',
        'slug',
        'group_type',
        'recruiting_status',
        'primary_focuses',
        'experience_expectation',
        'voice_expectation',
        'preferred_languages',
        'tags',
        'active_timezone',
        'active_days',
        'active_start_time',
        'active_end_time',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_visible' => 'boolean',
        'primary_focuses' => 'array',
        'preferred_languages' => 'array',
        'tags' => 'array',
        'active_days' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_memberships')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_follows')
            ->withPivot(['notifications_enabled'])
            ->withTimestamps();
    }

    public function invites(): HasMany
    {
        return $this->hasMany(GroupInvite::class);
    }

    public function bans(): HasMany
    {
        return $this->hasMany(GroupBan::class);
    }

    public function userNotes(): HasMany
    {
        return $this->hasMany(GroupUserNote::class);
    }

    public function systemInvite(): HasOne
    {
        return $this->hasOne(GroupInvite::class)->where('is_system', true);
    }

    public function scheduledRuns(): HasMany
    {
        return $this->hasMany(ScheduledRun::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId !== null && $this->owner_id === $userId;
    }

    public function hasModeratorAccess(?int $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        return $this->memberships
            ->contains(fn (GroupMembership $membership) => $membership->user_id === $userId
                && in_array($membership->role, [
                    GroupMembership::ROLE_ADMIN,
                    GroupMembership::ROLE_MODERATOR,
                ], true));
    }

    public function hasAdminAccess(?int $userId): bool
    {
        if ($this->isOwnedBy($userId)) {
            return true;
        }

        if ($userId === null) {
            return false;
        }

        return $this->memberships
            ->contains(fn (GroupMembership $membership) => $membership->user_id === $userId
                && $membership->role === GroupMembership::ROLE_ADMIN);
    }

    public function hasMember(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->memberships
            ->contains(fn (GroupMembership $membership) => $membership->user_id === $userId);
    }

    public function hasFollower(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if ($this->relationLoaded('followers')) {
            return $this->followers
                ->contains(fn (User $user) => $user->id === $userId);
        }

        return $this->followers()
            ->where('users.id', $userId)
            ->exists();
    }

    public function isBanned(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        if ($this->relationLoaded('bans')) {
            return $this->bans->contains(fn (GroupBan $ban) => $ban->user_id === $userId);
        }

        return $this->bans()
            ->where('user_id', $userId)
            ->exists();
    }

    public function usesCommunityJoinFlow(): bool
    {
        return $this->group_type === self::TYPE_COMMUNITY;
    }

    public function inferredRegion(): ?string
    {
        return self::regionForDatacenter($this->datacenter);
    }

    public static function regionForDatacenter(?string $datacenter): ?string
    {
        if ($datacenter === null) {
            return null;
        }

        return config("datacenters.regions.$datacenter");
    }

    public function ensureSystemInvite(): void
    {
        if (! $this->usesCommunityJoinFlow()) {
            return;
        }

        $this->invites()->updateOrCreate(
            ['is_system' => true],
            [
                'created_by' => null,
                'token' => $this->slug,
                'max_uses' => null,
                'expires_at' => null,
            ]
        );
    }

    public function removeSystemInvite(): void
    {
        $this->invites()->where('is_system', true)->delete();
    }
}
