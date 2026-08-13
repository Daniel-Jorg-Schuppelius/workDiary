<?php

return [
    // Statuts intermédiaires (MVP-532) : télétravail/course de service.
    'intermediate' => [
        'homeoffice' => 'Télétravail',
        'errand' => 'Course de service',
        'start_homeoffice' => 'Commencer le télétravail',
        'end_homeoffice' => 'Terminer le télétravail',
        'start_errand' => 'Commencer la course',
        'end_errand' => 'Terminer la course',
    ],
    'status' => [
        'open' => 'Ouvert',
        'closed' => 'Fermé',
        'auto_closed' => 'Fermé automatiquement',
        'adjusted' => 'Ajusté',
        'cancelled' => 'Annulé',
    ],
    'source' => [
        'clock' => 'Pointage',
        'manual' => 'Manuel',
        'import' => 'Import',
        'auto_close' => 'Fermeture auto',
        'terminal' => 'Terminal',
        'phone' => 'Téléphone',
    ],
    'correction' => [
        'action' => [
            'create' => 'Créer',
            'update' => 'Modifier',
            'delete' => 'Supprimer',
        ],
    ],
];
