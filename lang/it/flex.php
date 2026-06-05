<?php

return [
    'eligibility' => [
        'title' => 'Idoneità flex per :name',
        'nav_title' => 'Idoneità flex',
        'subtitle' => 'Periodi durante i quali :name partecipa al monitoraggio del tempo flex.',
        'current' => [
            'active' => 'Attualmente idoneo al flex',
            'inactive' => 'Attualmente non idoneo al flex',
        ],
        'table' => [
            'valid_from' => 'Valido dal',
            'valid_to' => 'Valido fino al',
            'open' => 'a tempo indeterminato',
            'note' => 'Nota',
            'actions' => 'Azioni',
        ],
        'form' => [
            'add_title' => 'Aggiungi nuovo periodo',
            'valid_from' => 'Valido dal',
            'valid_to' => 'Valido fino al (vuoto = a tempo indeterminato)',
            'note' => 'Nota (facoltativo)',
            'submit' => 'Crea periodo',
            'end_today' => 'Termina oggi',
            'end_submit' => 'Termina',
        ],
        'flash' => [
            'saved' => 'Periodo flex salvato.',
            'deleted' => 'Periodo flex eliminato.',
        ],
        'empty' => ':name non ha periodi flex registrati — non partecipa al tempo flex.',
        'confirm_delete' => 'Eliminare davvero questo periodo? I calcoli del saldo verranno rieseguiti.',
    ],
];
