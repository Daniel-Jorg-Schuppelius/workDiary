<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

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
        'attendances' => 'Timbrature',
        'project_times' => 'Tempi di progetto',
    ],
    'template' => [
        'example_required' => 'Valore di esempio (obbligatorio)',
        'example_optional' => 'Valore di esempio (facoltativo)',
        'download' => 'Scarica il modello',
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
        'periodLocked' => 'Periodo bloccato',
        'skipped' => 'Ignorato',
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
            'xlsxUnreadable' => 'Il file Excel è danneggiato o non è un formato XLSX valido.',
            'xlsxEmpty' => 'Il primo foglio di lavoro del file Excel non contiene righe.',
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
            'project' => 'Nessun progetto «:value» trovato — riga inviata alla casella di assegnazione.',
        ],
        'persist' => [
            'noBookingUser' => 'Nessun utente imputabile trovato nell\'organizzazione.',
        ],
        // MVP-438: blocco GoBD — nessuna sovrascrittura silenziosa di periodi verificati.
        'periodLocked' => [
            'attendance' => 'Il giorno :date è bloccato dalla chiusura giornaliera o dall\'approvazione mensile — riga ignorata.',
            'projectTime' => 'Il periodo :date è già chiuso/esportato — riga ignorata.',
        ],
        // MVP-438: righe di avviso iCal (mappatura volutamente prudente).
        'ical' => [
            'allDay' => 'Evento di intera giornata «:event» ignorato (non conteggiabile come presenza).',
            'noTime' => 'Evento «:event» senza orario ignorato.',
            'category' => 'Evento «:event» fuori dall\'elenco di categorie consentite ignorato.',
            'transparent' => 'Evento «:event» contrassegnato come libero/assente ignorato.',
            'recurring' => 'Evento ricorrente «:event»: importata solo l\'istanza base (l\'espansione della serie verrà in seguito).',
            'unsupportedEntity' => 'L\'importazione iCal non è supportata per questo tipo di importazione.',
        ],
    ],
];
