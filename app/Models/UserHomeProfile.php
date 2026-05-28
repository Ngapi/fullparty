<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'display_character_class_id',
    'description',
    'background_image_url',
])]
class UserHomeProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayCharacterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'display_character_class_id');
    }
}
