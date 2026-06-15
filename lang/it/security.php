<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : security.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Sicurezza',
    ],

    'subtitle' => 'Panoramica in sola lettura dello stato rilevante per la sicurezza: sessioni attive, token API, integrazioni esterne, ultimi export e accessi del supporto.',

    'scope' => [
        'label' => 'Ambito',
        'platform' => "A livello di piattaforma",
    ],

    'privacy_notice' => 'Questa pagina mostra solo metadati. Valori dei token, hash, segreti, password o contenuti delle sessioni non vengono mai mostrati. Tutti i dati rimangono locali.',

    'deferred_notice' => 'Le esecuzioni automatiche di cancellazione e conservazione non fanno parte di questa panoramica e seguiranno in un passaggio successivo (Funzionalità 016, «Più tardi»).',

    'section' => [
        'sessions' => 'Sessioni attive',
        'tokens' => 'Token API',
        'integrations' => 'Integrazioni esterne',
        'exports' => 'Ultimi export',
        'support_access' => 'Ultimi accessi del supporto',
        'two_factor' => 'Autenticazione a due fattori',
        'encryption' => 'Cifratura (a riposo)',
    ],

    'field' => [
        'user' => 'Utente',
        'guest' => 'Non connesso',
        'ip' => 'Indirizzo IP',
        'user_agent' => 'User agent',
        'last_activity' => 'Ultima attività',
        'sessions_total' => 'Sessioni totali',
        'sessions_active' => 'Di cui attive (< 2 h)',
        'token_name' => 'Nome',
        'abilities' => 'Autorizzazioni',
        'last_used_at' => 'Ultimo utilizzo',
        'expires_at' => 'Scade',
        'created_at' => 'Creato',
        'tokens_total' => 'Token totali',
        'plugins_active' => 'Plugin attivi',
        'external_references' => 'Riferimenti esterni',
        'export_kind' => 'Tipo',
        'export_subject' => 'Oggetto',
        'format' => 'Formato',
        'status' => 'Stato',
        'rows' => 'Record',
        'event' => 'Evento',
        'subject' => 'Oggetto',
        'users_total' => 'Utenti totali',
        'users_with_2fa' => 'Con 2FA attiva',
        'credentials' => 'Fattori confermati',
        'coverage' => 'Copertura',
        'encrypted_fields' => 'Campi cifrati',
        'table' => 'Tabella',
        'fields' => 'Campi',
    ],

    'export' => [
        'kind' => [
            'data_transfer' => 'Trasferimento dati',
            'time' => 'Export dei tempi',
        ],
    ],

    'status' => [
        'active' => 'attivo',
        'inactive' => 'inattivo',
        'app_key_set' => 'APP_KEY impostata',
        'app_key_missing' => 'APP_KEY mancante',
    ],

    'hint' => [
        'sessions_driver' => 'Driver di sessione «:driver» — nessuna panoramica del database disponibile. Solo il driver «database» fornisce un elenco di sessioni.',
        'tokens_no_secret' => 'Vengono mostrati solo i metadati — mai il valore del token né il suo hash.',
        'support_access' => "Origine: registro di audit, prefisso evento «support.» (vedi i principi di accesso del supporto).",
        'two_factor' => 'Semplice conteggio dei fattori confermati — nessun segreto viene letto.',
        'encryption' => "Questi campi vengono cifrati tramite «php artisan :command». La cifratura dipende dall'APP_KEY.",
    ],

    'empty' => [
        'sessions' => 'Nessuna sessione trovata.',
        'tokens' => 'Nessun token API attivo.',
        'integrations' => 'Nessuna integrazione esterna attiva.',
        'exports' => 'Nessun export registrato finora.',
        'support_access' => 'Nessun accesso del supporto registrato.',
    ],

    'generated_at' => 'Generato: :at',
];
