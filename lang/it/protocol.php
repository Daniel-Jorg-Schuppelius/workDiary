<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : protocol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
            'reordered' => 'Ordine aggiornato.',
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
        'photos' => ['more' => ':count altra/e foto'],
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
        'tokenRevoked' => 'Il link di firma è stato revocato.',
        'tokenList' => 'Link di firma esterni',
        'tokenUsed' => 'utilizzato',
        'tokenOpen' => 'aperto',
        'revoke' => 'Revoca link',
        'externalLink' => 'Link esterno',
        'tokenExpired' => 'Il link di firma è scaduto o è già stato utilizzato.',
        'tokenUnknown' => 'Link di firma sconosciuto.',
        'redeemed' => 'La firma è stata salvata.',
        'rejected' => 'Il suo rifiuto è stato registrato. I punti segnalati sono stati inoltrati.',
        'alreadyDecided' => 'È già stata presa una decisione su questo elemento.',
        'customer' => 'Cliente',
        'approveHeading' => 'Approva e firma',
        'rejectHeading' => 'Rifiuta',
        'rejectHint' => 'Motivare il rifiuto. I singoli difetti vengono registrati come punti aperti.',
        'rejectReason' => 'Motivo',
        'rejectIssues' => 'Singoli difetti (un punto per riga, facoltativo)',
        'rejectIssuesPlaceholder' => 'es. giunto della finestra non a tenuta',
        'rejectSubmit' => 'Rifiuta',
        'rejectIssueDescription' => 'Segnalato dal cliente al rifiuto dell’elemento «:protocol» (:name).',
        'queryHeading' => 'Poni una domanda',
        'queryQuestion' => 'La tua domanda',
        'querySubmit' => 'Invia domanda',
        'queryRaised' => 'La tua domanda è stata inviata.',
        'queryHistory' => 'Le tue domande',
        'queryAnswer' => 'Risposta',
        'queryPending' => 'Non ancora risposto.',
    ],
];
