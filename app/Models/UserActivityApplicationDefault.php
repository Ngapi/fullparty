<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityApplicationDefault extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type_id',
        'selected_character_id',
        'answers',
        'notes',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    public function selectedCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'selected_character_id');
    }
}
