<?php

return [
    'csv' => [
        'unreadable' => 'Il file non è leggibile.',
        'header_missing' => 'Riga di intestazione mancante o illeggibile: :error',
        'name_column_missing' => 'Colonna obbligatoria «Name» non trovata.',
    ],
    'routing' => [
        'nominatim_missing_coords' => 'La risposta di Nominatim non contiene coordinate.',
        'nominatim_http' => 'Nominatim ha restituito HTTP :status.',
    ],
    'upload' => [
        'too_large' => 'Il file è troppo grande (max. :max KB).',
        'type_not_allowed' => 'Tipo di file non consentito.',
    ],

    // Pagine di errore HTTP (041-P0, MVP-053)
    'request_id' => 'ID richiesta',
    'report_problem' => 'Segnala un problema',
    '404' => [
        'title' => 'Pagina non trovata',
        'message' => 'La pagina richiesta non esiste o è stata spostata.',
    ],
    '403' => [
        'title' => 'Accesso negato',
        'message' => "Non hai l'autorizzazione per questa azione. Contatta la tua amministrazione.",
    ],
    '419' => [
        'title' => 'Sessione scaduta',
        'message' => 'La pagina è rimasta aperta troppo a lungo. Ricaricala e riprova.',
    ],
    '500' => [
        'title' => 'Errore interno',
        'message' => "Si è verificato un errore imprevisto. Riprova più tardi o segnala il problema con l'ID richiesta.",
    ],
];
