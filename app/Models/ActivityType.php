<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivityType extends Model
{
    use HasFactory;

    public const DIFFICULTY_NORMAL = 'normal';

    public const DIFFICULTY_EXTREME = 'extreme';

    public const DIFFICULTY_UNREAL = 'unreal';

    public const DIFFICULTY_EXPLORATION = 'exploration';

    public const DIFFICULTY_SAVAGE = 'savage';

    public const DIFFICULTY_ULTIMATE = 'ultimate';

    public const DIFFICULTY_CHAOTIC = 'chaotic';

    public const DIFFICULTY_CRITERION = 'criterion';

    public const DIFFICULTIES = [
        self::DIFFICULTY_NORMAL,
        self::DIFFICULTY_EXTREME,
        self::DIFFICULTY_UNREAL,
        self::DIFFICULTY_EXPLORATION,
        self::DIFFICULTY_SAVAGE,
        self::DIFFICULTY_ULTIMATE,
        self::DIFFICULTY_CHAOTIC,
        self::DIFFICULTY_CRITERION,
    ];

    protected $fillable = [
        'slug',
        'draft_name',
        'draft_description',
        'draft_small_image_url',
        'draft_banner_image_url',
        'draft_difficulty',
        'draft_default_min_item_level',
        'draft_layout_schema',
        'draft_slot_schema',
        'draft_application_schema',
        'draft_roster_summary_presets',
        'draft_progress_schema',
        'draft_bench_size',
        'draft_prog_points',
        'draft_fflogs_zone_id',
        'is_active',
        'created_by_user_id',
        'current_published_version_id',
    ];

    protected $casts = [
        'draft_name' => 'array',
        'draft_description' => 'array',
        'draft_default_min_item_level' => 'integer',
        'draft_layout_schema' => 'array',
        'draft_slot_schema' => 'array',
        'draft_application_schema' => 'array',
        'draft_roster_summary_presets' => 'array',
        'draft_progress_schema' => 'array',
        'draft_bench_size' => 'integer',
        'draft_prog_points' => 'array',
        'draft_fflogs_zone_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function currentPublishedVersion(): BelongsTo
    {
        return $this->belongsTo(ActivityTypeVersion::class, 'current_published_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ActivityTypeVersion::class)->orderByDesc('version');
    }

    public function applicationDefaults(): HasMany
    {
        return $this->hasMany(UserActivityApplicationDefault::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ActivityTag::class, 'activity_type_activity_tag')
            ->withTimestamps()
            ->orderBy('name');
    }
}
