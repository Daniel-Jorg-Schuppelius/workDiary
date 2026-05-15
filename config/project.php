<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Standardprojekt pro Kunde
    |--------------------------------------------------------------------------
    |
    | Wird automatisch beim Anlegen eines Kunden erzeugt und dient als
    | Default-Bucket für ad-hoc-/Notfallaufträge ohne explizit gewähltes
    | Projekt. Pro Kunde existiert genau ein Standardprojekt.
    */
    'default_project' => [
        'name' => env('PROJECT_DEFAULT_NAME', 'Wartung'),
        'color' => env('PROJECT_DEFAULT_COLOR', '#64748b'),
        'billable' => (bool) env('PROJECT_DEFAULT_BILLABLE', true),
    ],
];
