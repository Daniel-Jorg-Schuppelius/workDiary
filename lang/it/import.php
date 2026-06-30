<?php

return [
    'entity' => [
        'customers' => 'Clienti',
        'suppliers' => 'Fornitori',
        'articles' => 'Articoli',
        'projects' => 'Progetti',
        'users' => 'Utenti',
        'materials' => 'Materiali',
        'vehicles' => 'Veicoli',
        'scheduled_shifts' => 'Piani turni',
        'tours' => 'Giri',
        'remote_sessions' => 'Sessioni di manutenzione remota',
    ],
    'state' => [
        'preflight' => 'Controllo preliminare',
        'awaitingApproval' => 'In attesa di approvazione',
        'running' => 'In corso',
        'succeeded' => 'Riuscito',
        'partial' => 'Parziale',
        'failed' => 'Fallito',
    ],
    'errorCode' => [
        'required' => 'Campo obbligatorio mancante',
        'format' => 'Errore di formato',
        'unique' => 'Valore non univoco',
        'fkMissing' => 'Riferimento non trovato',
        'tooLong' => 'Valore troppo lungo',
        'outOfRange' => 'Valore fuori intervallo',
        'persist' => 'Errore di persistenza',
        'headerMissing' => 'Colonna mancante',
        'headerUnknown' => 'Colonna sconosciuta',
    ],
    'error' => [
        'required' => 'Il campo obbligatorio :field è mancante.',
        'tooLong' => 'Il campo :field supera la lunghezza massima di :max caratteri.',
        'header' => [
            'missing' => 'La colonna obbligatoria :column è mancante nell\'intestazione CSV.',
            'duplicate' => 'La colonna :column compare più volte.',
        ],
        'format' => [
            'default' => 'Il campo :field ha un formato non valido (:reason).',
            'email' => 'Indirizzo e-mail non valido.',
            'country' => 'Il codice paese deve avere 2-3 lettere maiuscole (ISO 3166-1).',
            'currency' => 'Il codice valuta deve avere 3 lettere maiuscole (ISO 4217).',
            'enum' => 'Il valore non è uno stato valido.',
            'parse' => 'Impossibile analizzare il file: :reason',
            'date' => 'Data non valida (atteso es. «28.05.2026, 09:42:09»).',
            'time' => 'Ora non valida (atteso HH:MM).',
            'status' => 'Il valore non è uno stato valido.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Limite di righe (:max) superato — resto ignorato.',
        ],
        'fkMissing' => [
            'customer' => 'Nessun cliente con il numero :number trovato.',
            'user' => 'Nessun utente con l\'e-mail :value trovato.',
        ],
        'persist' => [
            'noBookingUser' => 'Nessun utente imputabile trovato nell\'organizzazione.',
        ],
    ],
];
