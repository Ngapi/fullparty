<?php

return [
    'activities' => [
        'management' => [
            'messages' => [
                'application_cancelled_assignment' => 'Diese Bewerbung wurde abgebrochen und aus der Warteschlange entfernt.',
                'application_no_longer_pending_assignment' => 'Diese Bewerbung ist nicht mehr ausstehend und wurde aus der Warteschlange entfernt.',
                'missing_application_cancelled' => 'Dieser Spieler hat seine Anmeldung abgebrochen, daher wurde der Fehlend-Eintrag entfernt.',
            ],
        ],
    ],
    'membership_applications' => [
        'apply' => [
            'validation' => [
                'pending_exists' => 'Du hast bereits eine offene Anfrage fuer diese Gruppe.',
            ],
        ],
        'review' => [
            'validation' => [
                'already_reviewed' => 'Diese Anfrage wurde bereits geprueft.',
                'applicant_banned' => 'Gesperrte Benutzer koennen nicht in die Gruppe aufgenommen werden.',
            ],
        ],
        'form' => [
            'validation' => [
                'fields_required' => 'Gib mindestens eine Frage an.',
                'minimum_fields' => 'Anfrageformulare benoetigen mindestens eine Frage.',
                'max_fields' => 'Anfrageformulare koennen bis zu :max Fragen haben.',
                'field_invalid' => 'Jede Frage muss ein gueltiges Objekt sein.',
                'type_invalid' => 'Waehle einen gueltigen Eingabetyp.',
                'name_required' => 'Der englische Name ist erforderlich.',
                'localized_text_invalid' => 'Lokalisierter Text muss Text sein.',
                'localized_text_max' => 'Lokalisierter Text darf hoechstens :max Zeichen lang sein.',
                'options_required' => 'Auswahlfragen benoetigen mindestens eine Option.',
                'options_max' => 'Auswahlfragen koennen bis zu :max Optionen haben.',
                'option_invalid' => 'Jede Option muss ein gueltiges Objekt sein.',
                'answer_unknown' => 'Diese Antwort passt nicht zum aktuellen Anfrageformular.',
                'answer_required' => 'Diese Antwort ist erforderlich.',
                'answer_invalid' => 'Gib eine gueltige Antwort an.',
                'answer_max' => 'Diese Antwort darf hoechstens :max Zeichen lang sein.',
                'answer_option_invalid' => 'Waehle eine der verfuegbaren Optionen.',
            ],
        ],
    ],
];
