<?php

return [
    'vehicle' => [
        'type' => [
            'car' => 'PKW',
            'van' => 'Transporter',
            'truck' => 'LKW',
            'bicycle' => 'Fahrrad',
            'other' => 'Sonstiges',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Benzin',
            'gas' => 'Gas',
            'hybrid' => 'Hybrid',
            'electric' => 'Elektro',
            'muscle' => 'Muskelkraft',
            'other' => 'Sonstiges',
        ],
        'ownership' => [
            'owned' => 'Eigentum',
            'leased' => 'Leasing',
            'rental' => 'Mietwagen',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Erstbescheinigung',
            'follow_up' => 'Folgebescheinigung',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Entwurf',
            'planned' => 'Geplant',
            'in_progress' => 'In Arbeit',
            'completed' => 'Abgeschlossen',
            'cancelled' => 'Abgebrochen',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Verwaltung',
            'training' => 'Schulung',
            'meeting' => 'Besprechung',
            'internal' => 'Intern',
            'travel' => 'Reise',
            'break' => 'Pause',
            'absence' => 'Abwesenheit',
            'standby' => 'Bereitschaft',
            'other' => 'Sonstiges',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Urlaub',
            'sick' => 'Krank',
            'special' => 'Sonderurlaub',
            'unpaid' => 'Unbezahlt',
        ],
        'status' => [
            'pending' => 'Ausstehend',
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
            'cancelled' => 'Storniert',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Aktiv',
            'paused' => 'Pausiert',
            'archived' => 'Archiviert',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Offen',
            'in_progress' => 'In Arbeit',
            'done' => 'Erledigt',
        ],
        'priority' => [
            'low' => 'Niedrig',
            'medium' => 'Mittel',
            'high' => 'Hoch',
            'urgent' => 'Dringend',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Entwurf',
            'submitted' => 'Eingereicht',
            'signed' => 'Signiert',
            'locked' => 'Gesperrt',
        ],
        'kind' => [
            'project' => 'Projekt',
            'personal_day' => 'Persönlicher Tag',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Arbeit',
            'travel' => 'Anfahrt',
            'standby' => 'Bereitschaft',
        ],
    ],
];
