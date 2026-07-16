<?php
/*
 * Created on   : Wed Jul 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sessions.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Utenti connessi',
    ],

    'subtitle' => 'Chi è connesso e dove — sessioni attive e accessi API per utente, con possibilità di disconnessione da remoto.',

    'privacy_notice' => 'Vengono mostrati solo i metadati (IP, dispositivo, orari) — mai il contenuto delle sessioni né i valori dei token.',

    'hint' => [
        'driver' => 'Le sessioni sono elencabili solo con il driver database; driver attuale: :driver. Senza il driver database non è possibile una disconnessione remota mirata.',
        'terminals' => 'I terminali di timbratura sono dispositivi fisici (non un login utente). "Disattiva" blocca il dispositivo, non disconnette un utente.',
        'remote_support' => 'Sessioni di assistenza remota importate — solo cronologia; non terminabili da workDiary.',
    ],

    'stat' => [
        'users' => 'Utenti',
        'online' => 'Online',
        'sessions' => 'Sessioni',
        'tokens' => 'Token API',
    ],

    'badge' => [
        'online' => 'Online',
        'this_device' => 'Questo dispositivo',
    ],

    'section' => [
        'sessions' => 'Sessioni web/app',
        'tokens' => 'Token API',
        'devices' => 'Dispositivi di localizzazione',
        'terminals' => 'Terminali di timbratura',
        'remote_support' => 'Assistenza remota recente',
    ],

    'col' => [
        'device' => 'Dispositivo',
        'ip' => 'IP',
        'last_activity' => 'Ultima attività',
        'name' => 'Nome',
        'created' => 'Creato',
        'last_used' => 'Ultimo utilizzo',
        'action' => 'Azione',
        'terminal' => 'Terminale',
        'status' => 'Stato',
        'last_seen' => 'Visto l\'ultima volta',
        'provider' => 'Fornitore',
        'remote' => 'Identificativo',
        'started' => 'Inizio',
        'ended' => 'Fine',
    ],

    'terminal' => [
        'inactive' => 'Disattivato',
        'offline' => 'Offline',
    ],

    'last_login' => 'Ultimo accesso',

    'live' => [
        'changed' => 'Gli accessi attivi sono cambiati.',
        'reload' => 'Ricarica elenco',
    ],

    'action' => [
        'revoke_all' => 'Disconnetti tutti i dispositivi',
        'revoke_session' => 'Disconnetti',
        'revoke_token' => 'Revoca',
        'revoke_device' => 'Scollega',
        'deactivate_terminal' => 'Disattiva',
    ],

    'confirm' => [
        'revoke_all' => 'Disconnettere :name da tutti i dispositivi? Le sessioni esistenti e "resta connesso" verranno invalidate.',
        'revoke_session' => 'Disconnettere davvero questa sessione da remoto?',
        'revoke_token' => 'Revocare davvero questo token API?',
        'revoke_device' => 'Scollegare davvero questo dispositivo di localizzazione?',
        'deactivate_terminal' => 'Disattivare davvero il terminale ":name"? Il dispositivo non potrà più accedere.',
    ],

    'empty' => [
        'title' => 'Nessun accesso attivo.',
        'description' => 'Al momento nessuno in questa organizzazione è connesso.',
    ],

    'error' => [
        'own_current_session' => 'La propria sessione attuale non può essere terminata qui — usa il logout normale.',
        'session_gone' => 'La sessione non esiste più.',
        'token_gone' => 'Il token non esiste più.',
        'device_gone' => 'Il dispositivo non esiste più o è già scollegato.',
    ],

    'flash' => [
        'session_revoked' => 'Sessione disconnessa da remoto.',
        'all_revoked' => ':name è stato disconnesso da tutti i dispositivi.',
        'token_revoked' => 'Token API revocato.',
        'device_revoked' => 'Dispositivo di localizzazione scollegato.',
        'terminal_deactivated' => 'Terminale disattivato.',
    ],
];
