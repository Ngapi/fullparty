<?php

use App\Http\Middleware\ApplyLocale;

return [
    'recruiting_statuses' => [
        'looking_for_members',
        'applications_open',
        'closed',
    ],
    'primary_focuses' => [
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
        'casual',
        'midcore',
        'semi_hardcore',
        'hardcore',
        'mixed',
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
