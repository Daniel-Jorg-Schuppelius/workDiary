<?php

return [
    'tabs' => [
        'pagination' => 'Elenchi',
        'invoicing' => 'Fatturazione',
        'uploads' => 'Caricamenti',
        'validation' => 'Limiti di immissione',
        'notifications' => 'Notifiche',
        'ui' => 'Interfaccia',
        'routing' => 'Routing e mappe',
    ],
    'hint' => 'Lascia vuoto per usare il valore predefinito del sistema.',
    'pagination' => [
        'heading' => 'Dimensioni pagina',
        'description' => 'Numero di elementi per pagina negli elenchi.',
        'timesheets' => 'Fogli ore',
        'duty_plans' => 'Piani turni',
        'customers' => 'Clienti',
        'customer_search' => 'Ricerca clienti (digitazione predittiva)',
        'customer_attachments' => 'Allegati cliente',
        'organizations' => 'Organizzazioni',
        'tours' => 'Giri',
        'vehicles' => 'Veicoli',
        'tags' => 'Etichette',
        'archive' => 'Archivio',
        'dashboard_recent' => 'Dashboard: elementi recenti',
    ],
    'invoicing' => [
        'heading' => 'Valori predefiniti di fatturazione',
        'description' => 'Valori precompilati alla creazione di una nuova fattura.',
        'default_tax_rate' => 'Aliquota fiscale predefinita (%)',
        'default_currency' => 'Valuta predefinita (ISO-4217)',
        'time_unit' => 'Unità di tempo per le voci',
    ],
    'uploads' => [
        'heading' => 'Limiti di dimensione caricamento (KB)',
        'description' => 'Dimensioni massime di caricamento, in kilobyte.',
        'csv_import_kb' => 'Importazione CSV',
        'customer_attachment_kb' => 'Allegato cliente',
    ],
    'validation' => [
        'heading' => 'Limiti di immissione',
        'description' => 'Limiti di caratteri e intervallo per i campi del modulo.',
        'attendance' => [
            'heading' => 'Presenza',
            'note_max' => 'Nota, caratteri max',
            'device_max' => 'ID dispositivo, caratteri max',
            'break_minutes_max' => 'Pausa, minuti max',
        ],
        'tag' => [
            'heading' => 'Etichette',
            'name_max' => 'Nome etichetta, caratteri max',
        ],
        'comment' => [
            'heading' => 'Commenti',
            'body_max' => 'Corpo del commento, caratteri max',
        ],
        'duty_plan' => [
            'heading' => 'Piani turni',
            'note_max' => 'Nota, caratteri max',
        ],
    ],
    'notifications' => [
        'heading' => 'Notifiche push',
        'description' => 'Comportamento dei messaggi push.',
        'push' => [
            'body_truncate' => 'Anteprima del messaggio, caratteri max',
        ],
    ],
    'ui' => [
        'heading' => 'Comportamento dell\'interfaccia',
        'description' => 'Comportamento visivo e interattivo dell\'interfaccia.',
        'calendar' => [
            'heading' => 'Calendario',
            'slot_minutes' => 'Durata degli slot in minuti',
        ],
        'dashboard' => [
            'heading' => 'Dashboard',
            'recent_limit' => 'Numero di elementi recenti',
        ],
        'search' => [
            'heading' => 'Ricerca',
            'results_limit' => 'Limite di risultati predefinito',
        ],
    ],
    'reset' => 'Ripristina predefinito',
    'placeholder_default' => 'Predefinito :value',
    'routing' => [
        'nominatim' => [
            'heading' => 'Nominatim (geocodifica)',
            'base_url' => 'URL base',
            'email' => 'E-mail di contatto',
            'rate_limit_per_sec' => 'Richieste al secondo',
        ],
        'osrm' => [
            'heading' => 'OSRM (routing)',
            'base_url' => 'URL base',
            'profile' => 'Profilo (es. driving)',
            'timeout' => 'Timeout (secondi)',
        ],
        'tiles' => [
            'heading' => 'Tile mappa',
            'url' => 'Modello URL tile',
            'max_zoom' => 'Zoom massimo',
        ],
    ],
];
