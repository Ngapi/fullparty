<?php

return [
    'activities' => [
        'management' => [
            'messages' => [
                'application_cancelled_assignment' => 'Cette candidature a ete annulee et retiree de la file.',
                'application_no_longer_pending_assignment' => 'Cette candidature n\'est plus en attente et a ete retiree de la file.',
                'missing_application_cancelled' => 'Ce joueur a annule son inscription, l\'entree manquante a donc ete retiree.',
            ],
        ],
    ],
    'membership_applications' => [
        'apply' => [
            'validation' => [
                'pending_exists' => 'Vous avez deja une demande en attente pour ce groupe.',
            ],
        ],
        'review' => [
            'validation' => [
                'already_reviewed' => 'Cette demande a deja ete traitee.',
                'applicant_banned' => 'Les utilisateurs bannis ne peuvent pas etre acceptes dans le groupe.',
            ],
        ],
        'form' => [
            'validation' => [
                'fields_required' => 'Ajoutez au moins une question.',
                'minimum_fields' => 'Les formulaires de demande doivent avoir au moins une question.',
                'max_fields' => 'Les formulaires de demande peuvent avoir jusqu-a :max questions.',
                'field_invalid' => 'Chaque question doit etre un objet valide.',
                'type_invalid' => 'Choisissez un type de champ valide.',
                'name_required' => 'Le nom anglais est obligatoire.',
                'localized_text_invalid' => 'Le texte localise doit etre du texte.',
                'localized_text_max' => 'Le texte localise doit contenir :max caracteres ou moins.',
                'options_required' => 'Les questions a selection doivent avoir au moins une option.',
                'options_max' => 'Les questions a selection peuvent avoir jusqu-a :max options.',
                'option_invalid' => 'Chaque option doit etre un objet valide.',
                'answer_unknown' => 'Cette reponse ne correspond pas au formulaire actuel.',
                'answer_required' => 'Cette reponse est obligatoire.',
                'answer_invalid' => 'Saisissez une reponse valide.',
                'answer_max' => 'Cette reponse doit contenir :max caracteres ou moins.',
                'answer_option_invalid' => 'Choisissez une des options disponibles.',
            ],
        ],
    ],
];
