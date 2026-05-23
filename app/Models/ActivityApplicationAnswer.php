<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityApplicationAnswer extends Model
{
    use HasFactory;

    public const TEXT_VALUE_MAX_LENGTH = 255;

    public const TEXTAREA_VALUE_MAX_LENGTH = 5000;

    public const URL_VALUE_MAX_LENGTH = 2000;

    protected $fillable = [
        'activity_application_id',
        'question_key',
        'question_label',
        'question_type',
        'source',
        'value',
    ];

    protected $casts = [
        'question_label' => 'array',
        'value' => 'array',
    ];

    public function activityApplication(): BelongsTo
    {
        return $this->belongsTo(ActivityApplication::class);
    }
}
