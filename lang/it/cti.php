<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : cti.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Telefonia / CTI',
    'intro' => 'Le chiamate in arrivo da clienti noti vengono registrate come voce di comunicazione (solo metadati: direzione, numero, ora, durata — mai il contenuto). Il provider (sipgate ecc.) segnala le chiamate all\'URL del webhook generato qui sotto. WorkDiary non è un centralino.',

    'note' => [
        'subject_inbound' => 'Chiamata in arrivo da :number',
        'subject_outbound' => 'Chiamata in uscita verso :number',
    ],

    // Pop-up chiamata (MVP-118) — notifica in-app al dipendente il cui
    // interno opt-in è stato chiamato.
    'popup' => [
        'title_customer' => 'Chiamata da :name',
        'title_unknown' => 'Chiamata da :number',
        'message' => 'Chiamata in arrivo (:number).',
        'unknown_number' => 'numero sconosciuto',
    ],

    'profile' => [
        'heading' => 'Pop-up chiamata',
        'extension_label' => 'Il mio interno',
        'extension_help' => 'Quando qualcuno chiama questo numero ricevi un pop-up con il chiamante e — se noto — un link alla scheda cliente. Lascia vuoto per nessun pop-up.',
        'extension_placeholder' => 'es. +49 30 1234-56',
        'invalid' => 'Inserisci un numero di telefono valido.',
    ],

    'new_heading' => 'Nuovo URL webhook',
    'new_hint' => 'Inseriscilo ora nel centralino/provider — il token viene mostrato solo questa volta.',

    'issue_heading' => 'Emetti una connessione',
    'connections_heading' => 'Connessioni',
    'no_connections' => 'Nessuna connessione emessa finora.',

    'field' => [
        'name' => 'Etichetta',
        'name_placeholder' => 'ad es. Reception sipgate',
        'provider' => 'Provider',
    ],

    'action' => [
        'issue' => 'Emetti',
        'disconnect' => 'Disattiva',
    ],

    'col' => [
        'status' => 'Stato',
        'last_event' => 'Ultimo evento',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
    ],

    'flash' => [
        'issued' => 'Connessione CTI emessa.',
        'disconnected' => 'Connessione CTI disattivata.',
    ],
];
