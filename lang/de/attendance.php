<?php

return [
    // Zwischen-Status (MVP-532): Homeoffice/Dienstgang.
    'intermediate' => [
        'homeoffice' => 'Homeoffice',
        'errand' => 'Dienstgang',
        'start_homeoffice' => 'Homeoffice beginnen',
        'end_homeoffice' => 'Homeoffice beenden',
        'start_errand' => 'Dienstgang beginnen',
        'end_errand' => 'Dienstgang beenden',
    ],
    'status' => [
        'open' => 'Offen',
        'closed' => 'Abgeschlossen',
        'auto_closed' => 'Auto-abgeschlossen',
        'adjusted' => 'Angepasst',
        'cancelled' => 'Storniert',
    ],
    'source' => [
        'clock' => 'Stempelung',
        'manual' => 'Manuell',
        'import' => 'Import',
        'auto_close' => 'Auto-Abschluss',
        'terminal' => 'Terminal',
        'phone' => 'Telefon',
    ],
    'correction' => [
        'action' => [
            'create' => 'Anlegen',
            'update' => 'Ändern',
            'delete' => 'Löschen',
        ],
    ],
];
