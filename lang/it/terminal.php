<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : terminal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Terminali di timbratura',
    'intro' => 'Terminali RFID/NFC fissi permettono ai dipendenti senza dispositivo aziendale di timbrare entrata e uscita. Gli eventi confluiscono nella stessa logica di presenza delle timbrature da browser (correzioni, report). I token del dispositivo e gli identificativi dei badge vengono memorizzati solo come hash.',

    'new_heading' => 'URL di ingest del terminale',
    'new_hint' => 'Inseriscilo ora nel terminale — il token viene mostrato solo questa volta.',

    'terminals_heading' => 'Terminali',
    'no_terminals' => 'Nessun terminale registrato finora.',
    'badges_heading' => 'Badge',
    'no_badges' => 'Nessun badge assegnato finora.',

    'field' => [
        'name' => 'Etichetta',
        'name_placeholder' => 'ad es. Capannone Nord',
        'site' => 'Sede',
        'no_site' => '— senza sede —',
    ],

    'badge' => [
        'user' => 'Dipendente',
        'label' => 'Etichetta',
        'uid' => 'Identificativo badge',
        'uid_placeholder' => 'UID RFID/NFC',
        'uid_help' => 'Memorizzato solo come hash (nessun identificativo in chiaro).',
        'validity' => 'Validità',
        'valid_from' => 'Valido dal',
        'valid_until' => 'Valido fino al',
        'outside_validity' => 'fuori validità',
    ],

    'action' => [
        'register' => 'Registra',
        'disable' => 'Disattiva',
        'assign' => 'Assegna',
        'revoke' => 'Revoca',
        'rotate' => 'Ruota token',
        'rotate_help' => 'Genera un nuovo token del dispositivo — il vecchio diventa subito non valido.',
    ],

    'col' => [
        'status' => 'Stato',
        'status_display' => 'Visualizzazione stato',
        'last_seen' => 'Ultima attività',
    ],

    'status_display' => [
        'on' => 'Attiva',
        'off' => 'Disattivata',
        'help' => 'Mostra saldo/ferie residue sul dispositivo dopo la timbratura (visibile ai presenti) — disattivata di default.',
    ],

    'buffer' => [
        'label' => 'Buffer',
        'help' => 'Eventi offline segnalati dal terminale non ancora trasmessi.',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Disattivato',
        'revoked' => 'Revocato',
    ],

    'flash' => [
        'registered' => 'Terminale registrato.',
        'terminal_disabled' => 'Terminale disattivato.',
        'badge_assigned' => 'Badge assegnato.',
        'badge_revoked' => 'Badge revocato.',
        'badge_taken' => 'Questo identificativo badge è già assegnato.',
        'token_rotated' => 'Token del dispositivo ruotato — nuovo URL di ingest visibile una sola volta.',
        'status_enabled' => 'Visualizzazione stato attivata.',
        'status_disabled' => 'Visualizzazione stato disattivata.',
    ],
    'kiosk' => [
        'pin_toggle' => 'Badge dimenticato? Timbra con PIN',
        'pin_submit' => 'Timbra',
        'heading' => 'Indirizzo del chiosco',
        'hint' => 'Aprilo nel browser di un tablet: il tablet diventa un terminale di timbratura. Contiene lo stesso token; mostrato una sola volta.',
        'title' => 'Terminale di timbratura',
        'intro' => 'Avvicina il badge al lettore.',
        'mode' => 'Tipo di timbratura',
        'mode_work' => 'Entrata / Uscita',
        'mode_break' => 'Pausa',
        'badge_label' => 'Badge',
        'nfc_start' => 'Usa l\'NFC di questo dispositivo',
        'nfc_active' => 'NFC in lettura',
        'flex_balance' => 'Flessibilità:',
        'status' => [
            'invalid_pin' => 'Matricola o PIN non validi',
            'clocked_in' => 'Entrata registrata',
            'clocked_out' => 'Uscita registrata',
            'break_started' => 'Pausa iniziata',
            'break_ended' => 'Pausa terminata',
            'noop' => 'Nessuna presenza aperta',
            'skipped' => 'Già registrato',
            'unknown_badge' => 'Badge sconosciuto',
            'rejected' => 'Timbratura rifiutata',
            'invalid_token' => 'Terminale disattivato',
            'unavailable' => 'Al momento non è possibile timbrare',
            'network' => 'Nessuna connessione: riprova',
            'nfc_unavailable' => 'NFC non disponibile su questo dispositivo',
            'error' => 'Errore nella timbratura',
        ],
    ],
    'checkpoint' => [
        'heading' => 'Punti di check-in (QR/NFC)',
        'intro' => 'Un codice presso la sede o il veicolo: il personale lo scansiona con il proprio dispositivo e timbra da connesso. Lo stesso indirizzo può essere scritto su un adesivo NFC.',
        'empty' => 'Ancora nessun punto di check-in.',
        'action' => [
            'create' => 'Crea punto di check-in',
            'qr' => 'Stampa codice QR',
            'enable' => 'Attiva',
        ],
        'field' => [
            'kind' => 'Tipo',
            'location' => 'Sede / veicolo',
            'vehicle' => 'Veicolo',
            'radius' => 'Raggio (m)',
            'location_check' => 'Verifica della posizione (facoltativa)',
            'latitude' => 'Latitudine',
            'longitude' => 'Longitudine',
        ],
        'help' => [
            'site' => 'Solo per il tipo «Sede».',
            'vehicle' => 'Obbligatorio per il tipo «Veicolo».',
            'location_check' => 'Un codice può essere fotografato. Con un raggio il dispositivo deve essere vicino al momento della timbratura; senza coordinate proprie valgono quelle della sede. La posizione non viene salvata.',
        ],
        'error' => [
            'radius_without_center' => 'Il raggio richiede una posizione: inserisci le coordinate o scegli una sede con coordinate.',
            'vehicle' => 'Veicolo non trovato.',
        ],
        'flash' => [
            'created' => 'Punto di check-in creato.',
            'enabled' => 'Punto di check-in attivato.',
            'disabled' => 'Punto di check-in disattivato.',
        ],
        'qr' => [
            'alt' => 'Codice QR per il check-in «:name»',
            'hint' => 'Scansiona con il telefono, accedi e conferma entrata o uscita.',
            'nfc_hint' => 'Per un adesivo NFC, scrivi questo indirizzo come indirizzo web (URL) con un\'app NFC.',
        ],
    ],
    'pin' => [
        'heading' => 'PIN del terminale',
        'intro' => 'Badge dimenticato? Con matricola e PIN si può timbrare comunque al terminale e al chiosco. Viene salvato solo un hash; dopo 5 tentativi falliti il PIN è bloccato per 15 minuti.',
        'empty' => 'Nessun PIN ancora assegnato.',
        'action' => [
            'set' => 'Imposta PIN',
            'unlock' => 'Sblocca',
            'remove' => 'Rimuovi',
        ],
        'field' => [
            'pin' => 'PIN',
            'pin_confirmation' => 'Ripeti PIN',
            'personnel_number' => 'Matricola',
        ],
        'help' => [
            'dialog' => 'Da 4 a 8 cifre. La persona riceve il PIN da te: in seguito non è più visibile.',
            'personnel_number' => 'Solo persone con matricola: è la seconda parte dell\'accesso al terminale.',
        ],
        'status' => [
            'locked_until' => 'bloccato fino alle :time',
        ],
        'confirm' => [
            'remove' => 'Rimuovere davvero il PIN? La persona potrà timbrare solo con il badge.',
        ],
        'error' => [
            'format' => 'Il PIN deve avere da :min a :max cifre.',
            'personnel_number' => 'La persona non ha una matricola: senza di essa il PIN non è utilizzabile al terminale.',
        ],
        'flash' => [
            'set' => 'PIN impostato.',
            'unlocked' => 'PIN sbloccato.',
            'removed' => 'PIN rimosso.',
        ],
    ],
];
