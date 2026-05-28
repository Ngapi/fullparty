<?php

return [
    'membership_applications' => [
        'apply' => [
            'validation' => [
                'pending_exists' => 'You already have a pending request for this group.',
            ],
        ],
        'review' => [
            'validation' => [
                'already_reviewed' => 'This request has already been reviewed.',
                'applicant_banned' => 'Banned users cannot be approved into the group.',
            ],
        ],
        'form' => [
            'validation' => [
                'fields_required' => 'Provide at least one question.',
                'minimum_fields' => 'Application forms need at least one question.',
                'max_fields' => 'Application forms can have up to :max questions.',
                'field_invalid' => 'Each question must be a valid object.',
                'type_invalid' => 'Choose a valid input type.',
                'name_required' => 'The English name is required.',
                'localized_text_invalid' => 'Localized text must be text.',
                'localized_text_max' => 'Localized text must be :max characters or fewer.',
                'options_required' => 'Select menu questions need at least one option.',
                'options_max' => 'Select menu questions can have up to :max options.',
                'option_invalid' => 'Each option must be a valid object.',
                'answer_unknown' => 'This answer does not match the current application form.',
                'answer_required' => 'This answer is required.',
                'answer_invalid' => 'Provide a valid answer.',
                'answer_max' => 'This answer must be :max characters or fewer.',
                'answer_option_invalid' => 'Choose one of the available options.',
            ],
        ],
    ],
];
