<?php

use App\Http\Middleware\ApplyLocale;

return [
    'recruiting_statuses' => [
        'open',
        'selective',
        'closed',
    ],
    'primary_focuses' => [
        'social_community',
        'progression',
        'clears',
        'reclears',
        'farming',
        'mount_farming',
        'maps',
        'casual_roulettes',
    ],
    'experience_expectations' => [
        'beginner_friendly',
        'mixed',
        'experienced_only',
    ],
    'voice_expectations' => [
        'required',
        'preferred',
        'optional',
    ],
    'active_days' => [
        'mon',
        'tue',
        'wed',
        'thu',
        'fri',
        'sat',
        'sun',
    ],
    'preferred_languages' => ApplyLocale::SUPPORTED_LOCALES,
    'max_tags' => 12,
];
