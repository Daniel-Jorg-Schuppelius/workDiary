<?php

return [
    'title' => [
        'index' => 'Protocolli',
        'show' => 'Protocollo #:id',
        'create' => 'Crea protocollo',
        'edit' => 'Modifica protocollo',
    ],
    'field' => [
        'type' => 'Tipo',
        'title' => 'Titolo',
        'description' => 'Descrizione',
        'state_initial' => 'Stato prima',
        'stateInitial' => 'Stato prima',
        'state_final' => 'Stato dopo',
        'stateFinal' => 'Stato dopo',
        'occurred_at' => 'Data / ora',
        'occurredAt' => 'Data / ora',
        'createdBy' => 'Creato da',
        'visibility' => 'Visibilità',
        'status' => 'Stato',
        'revision' => 'Revisione',
        'subject' => 'Riferimento',
    ],
    'action' => [
        'create' => 'Crea',
        'update' => 'Salva',
        'requestReview' => 'Invia per revisione',
        'returnToDraft' => 'Riporta a bozza',
        'sign' => 'Finalizza / firma',
        'archive' => 'Archivia',
        'supersede' => 'Crea revisione di correzione',
        'addItem' => 'Aggiungi elemento',
        'fillItem' => 'Compila elemento',
        'removeItem' => 'Rimuovi elemento',
        'delete' => 'Elimina',
    ],
    'flash' => [
        'created' => 'Protocollo creato.',
        'updated' => 'Protocollo aggiornato.',
        'deleted' => 'Protocollo eliminato.',
        'transition' => [
            'requestReview' => 'Protocollo inviato per revisione.',
            'returnToDraft' => 'Protocollo riportato a bozza.',
            'sign' => 'Protocollo firmato e finalizzato.',
            'archive' => 'Protocollo archiviato.',
            'supersede' => 'Revisione di correzione creata.',
        ],
        'item' => [
            'added' => 'Elemento aggiunto.',
            'filled' => 'Elemento compilato.',
            'removed' => 'Elemento rimosso.',
        ],
        'photo' => [
            'uploaded' => 'Foto aggiunta.',
            'removed' => 'Foto rimossa.',
            'captionUpdated' => 'Didascalia aggiornata.',
        ],
    ],
    'validation' => [
        'required' => 'L\'elemento «:label» è obbligatorio.',
        'criticalDefectMissingOpenIssue' => 'Il difetto critico «:label» richiede un punto aperto.',
        'text' => [
            'minLength' => 'Testo troppo breve (min. :min caratteri).',
            'maxLength' => 'Testo troppo lungo (max. :max caratteri).',
        ],
        'boolean' => [
            'invalid' => 'È richiesto un valore booleano.',
        ],
        'choice' => [
            'invalid' => 'È richiesta una selezione.',
            'notInOptions' => 'La selezione non è presente nell\'elenco delle opzioni.',
        ],
        'multichoice' => [
            'invalid' => 'È richiesta almeno una selezione.',
            'notInOptions' => 'La selezione non è presente nell\'elenco delle opzioni.',
        ],
        'number' => [
            'invalid' => 'Valore numerico richiesto.',
            'min' => 'Il valore è inferiore al minimo (:bound).',
            'max' => 'Il valore supera il massimo (:bound).',
        ],
        'date' => [
            'invalid' => 'Data non valida.',
        ],
        'attachments' => [
            'required' => 'È richiesto almeno un allegato.',
            'min' => 'Sono richiesti almeno :min allegati.',
            'max' => 'Sono consentiti al massimo :max allegati.',
        ],
        'defect' => [
            'severity' => 'La gravità deve essere low/medium/high/critical.',
            'description' => 'La descrizione del difetto è obbligatoria.',
        ],
        'measurement' => [
            'empty' => 'È richiesta almeno una misurazione.',
            'invalidSample' => 'Ogni misurazione necessita di «at» e «value».',
        ],
        'signature' => [
            'missing' => 'La firma non è ancora apposta.',
        ],
        'photo' => [
            'missingPhase' => 'Elemento foto «:label»: la fase «:phase» richiede almeno :need foto (presenti: :have).',
        ],
    ],
    'pdf' => [
        'title' => 'Protocollo – :title',
        'state' => 'Stato',
        'items' => 'Elementi del protocollo',
        'signatures' => 'Firme',
        'col' => [
            'label' => 'Elemento',
            'type' => 'Tipo',
            'value' => 'Valore',
            'result' => 'Risultato',
            'note' => 'Nota',
        ],
        'footer' => [
            'hash' => 'Checksum',
            'generated' => 'Generato il :at',
        ],
    ],
    'signature' => [
        'tokenIssued' => 'Il link di firma è stato creato.',
        'tokenExpired' => 'Il link di firma è scaduto o è già stato utilizzato.',
        'tokenUnknown' => 'Link di firma sconosciuto.',
        'redeemed' => 'La firma è stata salvata.',
    ],
];
