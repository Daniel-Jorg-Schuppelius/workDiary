<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : day-close.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Chiusura giornaliera (MVP-015, docs/tagesabschluss.md) — testi della
 * pagina, messaggi del validatore (§4), messaggi flash ed errori.
 * Mantenuto in parità de/en/fr/it/es; le etichette degli enum si trovano
 * in enums.php (dayClosure.status / dayCorrection.status).
 */

return [
    'title' => 'Chiusura giornaliera',
    'title_day' => 'Chiusura giornaliera :day',

    'subtitle' => [
        'own' => 'Controllare la giornata, colmare le lacune e chiuderla come completamente registrata.',
        'other' => 'Chiusura giornaliera di :name.',
    ],

    'section' => [
        'attendance' => 'Presenza',
        'breaks' => 'Pause',
        'entries' => 'Tempi di commessa e progetto',
        'issues' => 'Lacune e avvisi',
        'balance' => 'Bilancio',
        'corrections' => 'Richieste di correzione',
    ],

    'field' => [
        'date' => 'Data',
        'recorded_break' => 'Pausa registrata',
        'required_break' => 'Pausa obbligatoria',
        'target' => 'Ore previste',
        'gross' => 'Presenza (lorda)',
        'break' => 'Pausa',
        'net' => 'Lavoro netto',
        'booked' => 'Registrato',
        'diff' => 'Differenza',
        'day_balance' => 'Saldo del giorno',
        'month_balance' => 'Saldo del mese corrente',
        'duration' => 'Durata',
        'project' => 'Commessa / progetto',
        'activity' => 'Attività',
        'comment' => 'Commento',
        'billable' => 'Fatturabile',
        'reason' => 'Motivazione',
        'reason_placeholder' => 'Motivazione (almeno :min caratteri)',
        'decision' => 'Decisione',
    ],

    'action' => [
        'prev_day' => 'Giorno precedente',
        'next_day' => 'Giorno successivo',
        'today' => 'Oggi',
        'pick_date' => 'Scegli una data',
        'show_day' => 'Mostra giorno',
        'clock_in' => 'Timbra ora',
        'clock_out' => 'Timbra l\'uscita ora',
        'book_time' => 'Registra tempo',
        'save' => 'Salva',
        'close_day' => 'Chiudi giornata',
        'request_correction' => 'Richiedi correzione',
        'reopen' => 'Riapri giornata',
        'approve' => 'Approva',
        'reject' => 'Rifiuta',
        'cancel' => 'Annulla',
    ],

    'status' => [
        'attendance_open' => 'aperto',
        'comment_missing' => 'mancante',
        'billable' => 'fatturabile',
    ],

    'hint' => [
        'no_attendance' => 'Nessuna timbratura in questo giorno.',
        'attendance_correction_only' => 'Le timbrature possono essere modificate solo tramite una richiesta di correzione.',
        'attendance_locked' => 'Le timbrature di presenza sono bloccate dopo l\'approvazione di una correzione — fino alla nuova chiusura sono modificabili solo le registrazioni.',
        'no_entries' => 'Ancora nessuna registrazione in questo giorno.',
        'break_recorded' => 'Pausa: :min min',
        'no_issues' => 'Nessuna anomalia — la giornata è registrata in modo coerente.',
        'month_locked' => 'Questo giorno appartiene a un mese approvato ed è bloccato — chiusura e richieste di correzione passano per l\'approvazione mensile.',
        'correction_intro' => 'Descrivi cosa deve essere corretto in questo giorno.',
        'reopen_intro' => 'La giornata viene riaperta senza richiesta di correzione — la motivazione viene salvata nel registro di audit.',
    ],

    // I 7 controlli di coerenza del §4 — chiave = codice del controllo
    // (DayClosureValidator), i punti nel codice diventano annidamento.
    'check' => [
        'attendance' => [
            'missing_close' => 'Il timbracartellino è ancora aperto — si prega di timbrare l\'uscita.',
        ],
        'time' => [
            'unallocated_minutes' => ':minutes minuti di presenza non sono ancora assegnati a una registrazione.',
            'gap_in_attendance' => 'Lacuna di presenza di :minutes minuti senza indicatore di pausa.',
        ],
        'break' => [
            'required' => 'Pausa obbligatoria non rispettata: :taken minuti registrati su :required.',
        ],
        'balance' => [
            'threshold' => 'Il saldo del giorno di :hours ore supera ±2 h.',
        ],
        'entry' => [
            'missing_comment' => ':count registrazione/i fatturabile/i senza commento.',
        ],
        'worktime' => [
            'overrun' => 'Tempo di lavoro netto superiore a 10 ore (:minutes minuti, ArbZG).',
        ],
        'unknown' => 'Controllo sconosciuto: :code',
    ],

    'flash' => [
        'saved' => 'La giornata :day è stata salvata.',
        'closed' => 'La giornata :day è stata chiusa.',
        'correction_requested' => 'La correzione per :day è stata richiesta.',
        'correction_approved' => 'La correzione per :day è stata approvata.',
        'correction_rejected' => 'La correzione per :day è stata rifiutata.',
        'reopened' => 'La giornata :day è stata riaperta.',
    ],

    'errors' => [
        'future_day' => 'Un giorno futuro non può essere chiuso.',
        'blocking_warnings' => 'La giornata presenta avvisi bloccanti e non può essere chiusa.',
        'illegal_day_status' => 'Azione non consentita: lo stato del giorno è :status.',
        'illegal_request_status' => 'Azione non consentita: lo stato della richiesta è :status.',
        'reason_too_short' => 'È richiesta una motivazione di almeno :n caratteri.',
        'month_locked' => 'Il mese è già approvato o bloccato — riaprire prima l\'approvazione mensile.',
        'owner_missing' => 'Chiusura giornaliera senza proprietario valido.',
        'closure_missing' => 'Richiesta di correzione senza chiusura giornaliera associata.',
        // Motivazioni del tooltip per il pulsante di chiusura disattivato (§2.6).
        'close_blocked' => [
            'future' => 'Un giorno futuro non può essere chiuso.',
            'month_locked' => 'Il mese è già approvato o bloccato.',
            'blocking' => 'Gli avvisi bloccanti (⛔) devono prima essere risolti.',
            'not_open' => 'La giornata non è aperta.',
        ],
    ],

    'modal' => [
        'correction_title' => 'Richiedi correzione',
        'reopen_title' => 'Riapri giornata',
    ],
];
