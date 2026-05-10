<?php

namespace Database\Factories;

use App\Models\ActivityType;
use App\Models\ActivityTypeVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityTypeVersion>
 */
class ActivityTypeVersionFactory extends Factory
{
    protected $model = ActivityTypeVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_type_id' => ActivityType::factory(),
            'version' => 1,
            'name' => ['en' => fake()->words(2, true)],
            'description' => ['en' => fake()->sentence()],
            'small_image_url' => null,
            'banner_image_url' => null,
            'difficulty' => ActivityType::DIFFICULTY_NORMAL,
            'default_min_item_level' => null,
            'layout_schema' => [
                'groups' => [
                    [
                        'key' => 'party-a',
                        'label' => ['en' => 'Party A'],
                        'size' => 8,
                    ],
                ],
            ],
            'slot_schema' => [
                [
                    'key' => 'character_class',
                    'label' => ['en' => 'Character Class'],
                    'type' => 'single_select',
                    'source' => 'character_classes',
                ],
                [
                    'key' => 'phantom_job',
                    'label' => ['en' => 'Phantom Job'],
                    'type' => 'single_select',
                    'source' => 'phantom_jobs',
                ],
            ],
            'application_schema' => [
                'questions' => [
                    [
                        'key' => 'experience',
                        'label' => ['en' => 'Experience'],
                        'type' => 'textarea',
                        'required' => false,
                    ],
                ],
            ],
            'roster_summary_presets' => [],
            'progress_schema' => [
                'milestones' => [
                    [
                        'key' => 'clear',
                        'label' => ['en' => 'Clear'],
                        'order' => 1,
                    ],
                ],
            ],
            'prog_points' => [
                [
                    'key' => 'clear',
                    'label' => ['en' => 'Clear'],
                    'order' => 1,
                ],
            ],
            'published_by_user_id' => User::factory(),
            'published_at' => now(),
        ];
    }
}
