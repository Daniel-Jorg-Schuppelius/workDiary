<?php

return [
    'titles' => [
        'index' => 'Stundenzettel',
        'show' => 'Stundenzettel #:id',
    ],
    'fields' => [
        'date' => 'Datum',
        'project' => 'Projekt',
        'user' => 'Mitarbeiter',
        'status' => 'Status',
        'started_at' => 'Beginn',
        'ended_at' => 'Ende',
        'break_minutes' => 'Pause (min)',
        'duration' => 'Dauer',
        'kind' => 'Art',
        'description' => 'Beschreibung',
        'notes' => 'Notizen',
    ],
    'totals' => [
        'work' => 'Arbeit gesamt',
        'break' => 'Pause gesamt',
        'material_net' => 'Material netto gesamt',
    ],
    'sections' => [
        'entries' => 'Zeiteinträge',
        'materials' => 'Verbrauchsmaterial',
        'customer_release' => 'Kundenfreigabe',
        'notes' => 'Notizen',
    ],
    'signature' => [
        'signed_at' => 'Signiert am :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256: :hash',
        'none' => '— keine Unterschrift —',
    ],
];
