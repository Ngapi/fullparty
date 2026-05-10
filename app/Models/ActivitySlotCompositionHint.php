<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivitySlotCompositionHint extends Model
{
    use HasFactory;

    public const TYPE_ROLE = 'role';

    public const TYPE_CLASS = 'class';

    protected $fillable = [
        'activity_slot_id',
        'hint_type',
        'hint_key',
        'role_key',
        'character_class_id',
        'sort_order',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ActivitySlot::class, 'activity_slot_id');
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class);
    }
}
