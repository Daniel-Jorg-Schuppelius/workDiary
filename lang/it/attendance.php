<?php

return [
    // Stati intermedi (MVP-532): smart working/commissione di servizio.
    'intermediate' => [
        'homeoffice' => 'Smart working',
        'errand' => 'Commissione di servizio',
        'start_homeoffice' => 'Inizia smart working',
        'end_homeoffice' => 'Termina smart working',
        'start_errand' => 'Inizia commissione',
        'end_errand' => 'Termina commissione',
    ],
    'status' => [
        'open' => 'Aperto',
        'closed' => 'Chiuso',
        'auto_closed' => 'Chiuso automaticamente',
        'adjusted' => 'Rettificato',
        'cancelled' => 'Annullato',
    ],
    'source' => [
        'clock' => 'Timbratura',
        'manual' => 'Manuale',
        'import' => 'Importazione',
        'auto_close' => 'Chiusura automatica',
        'terminal' => 'Terminal',
        'phone' => 'Telefono',
    ],
    'correction' => [
        'action' => [
            'create' => 'Crea',
            'update' => 'Modifica',
            'delete' => 'Elimina',
        ],
    ],
];
