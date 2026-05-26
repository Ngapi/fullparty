<?php

use App\Http\Middleware\ApplyLocale;

return [
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
    'badge_colors' => [
        'primary_focuses' => [
            'progression' => '#7A5AF8',
            'clears' => '#4C7DFF',
            'reclears' => '#8B5CF6',
            'farming' => '#38BDF8',
            'mount_farming' => '#A78BFA',
            'maps' => '#6366F1',
            'casual_roulettes' => '#64748B',
        ],
        'experience_expectations' => [
            'beginner_friendly' => '#62C98F',
            'casual' => '#8CCB7A',
            'midcore' => '#D7B96A',
            'semi_hardcore' => '#D79A6A',
            'hardcore' => '#D77474',
            'mixed' => '#64748B',
        ],
        'voice_expectations' => [
            'required' => '#D77474',
            'preferred' => '#6FA7E8',
            'optional' => '#62C98F',
        ],
        'preferred_languages' => [
            'en' => '#4C7DFF',
            'de' => '#8B5CF6',
            'fr' => '#A855F7',
            'ja' => '#6366F1',
        ],
        'active_days' => [
            'mon' => '#64748B',
            'tue' => '#6366F1',
            'wed' => '#4C7DFF',
            'thu' => '#7A5AF8',
            'fri' => '#8B5CF6',
            'sat' => '#A855F7',
            'sun' => '#38BDF8',
        ],
        'regions' => [
            'EU' => '#38BDF8',
            'NA' => '#4C7DFF',
            'JP' => '#7A5AF8',
        ],
        'tag_palette' => [],
    ],
];
