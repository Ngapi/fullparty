<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    public const SAFE_SUMMARY_COLUMNS = [
        'id',
        'user_id',
        'provider',
        'provider_user_id',
        'provider_name',
        'provider_email',
        'avatar_url',
        'provider_data',
        'expires_at',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'provider_name',
        'provider_email',
        'avatar_url',
        'access_token',
        'refresh_token',
        'provider_data',
        'expires_at',
    ];

    protected $hidden = [
        'provider_user_id',
        'access_token',
        'refresh_token',
        'provider_data',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'provider_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSafeSummary(Builder $query): Builder
    {
        return $query->select(self::SAFE_SUMMARY_COLUMNS);
    }
}
