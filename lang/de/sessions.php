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
        'index' => 'Angemeldete Nutzer',
    ],

    'subtitle' => 'Wer ist gerade wo angemeldet — aktive Sitzungen und API-Zugänge je Nutzer, mit der Möglichkeit zum Fernabmelden.',

    'privacy_notice' => 'Angezeigt werden ausschließlich Metadaten (IP, Gerät, Zeitpunkte) — niemals Sitzungsinhalte oder Token-Werte.',

    'hint' => [
        'driver' => 'Sitzungen sind nur mit dem Datenbank-Treiber auflistbar; aktueller Treiber: :driver. Ohne database-Treiber ist kein gezieltes Fernabmelden möglich.',
        'terminals' => 'Stempelterminals sind physische Geräte (kein Nutzer-Login). „Deaktivieren" sperrt das Gerät, meldet keinen Nutzer ab.',
        'remote_support' => 'Importierte Fernwartungssitzungen — reine Historie; aus workDiary nicht beendbar.',
    ],

    'stat' => [
        'users' => 'Nutzer',
        'online' => 'Online',
        'sessions' => 'Sitzungen',
        'tokens' => 'API-Tokens',
    ],

    'badge' => [
        'online' => 'Online',
        'this_device' => 'Dieses Gerät',
    ],

    'section' => [
        'sessions' => 'Web-/App-Sitzungen',
        'tokens' => 'API-Tokens',
        'devices' => 'Standort-Geräte',
        'terminals' => 'Stempelterminals',
        'remote_support' => 'Letzte Fernwartungen',
    ],

    'col' => [
        'device' => 'Gerät',
        'ip' => 'IP',
        'last_activity' => 'Letzte Aktivität',
        'name' => 'Name',
        'created' => 'Angelegt',
        'last_used' => 'Zuletzt genutzt',
        'action' => 'Aktion',
        'terminal' => 'Terminal',
        'status' => 'Status',
        'last_seen' => 'Zuletzt gesehen',
        'provider' => 'Anbieter',
        'remote' => 'Kennung',
        'started' => 'Beginn',
        'ended' => 'Ende',
    ],

    'terminal' => [
        'inactive' => 'Deaktiviert',
        'offline' => 'Offline',
    ],

    'last_login' => 'Letzte Anmeldung',

    'live' => [
        'changed' => 'Es gibt Änderungen an den aktiven Anmeldungen.',
        'reload' => 'Liste neu laden',
    ],

    'action' => [
        'revoke_all' => 'Alle Geräte abmelden',
        'revoke_session' => 'Abmelden',
        'revoke_token' => 'Widerrufen',
        'revoke_device' => 'Trennen',
        'deactivate_terminal' => 'Deaktivieren',
    ],

    'confirm' => [
        'revoke_all' => ':name von allen Geräten abmelden? Bestehende Sitzungen und „angemeldet bleiben" werden entwertet.',
        'revoke_session' => 'Diese Sitzung wirklich fernabmelden?',
        'revoke_token' => 'Diesen API-Token wirklich widerrufen?',
        'revoke_device' => 'Dieses Standort-Gerät wirklich trennen?',
        'deactivate_terminal' => 'Terminal „:name" wirklich deaktivieren? Das Gerät kann sich dann nicht mehr anmelden.',
    ],

    'empty' => [
        'title' => 'Keine aktiven Anmeldungen.',
        'description' => 'Aktuell ist niemand in dieser Organisation angemeldet.',
    ],

    'error' => [
        'own_current_session' => 'Die eigene aktuelle Sitzung kann hier nicht beendet werden — nutze dafür den normalen Logout.',
        'session_gone' => 'Sitzung existiert nicht (mehr).',
        'token_gone' => 'Token existiert nicht (mehr).',
        'device_gone' => 'Gerät existiert nicht (mehr) oder ist bereits getrennt.',
    ],

    'flash' => [
        'session_revoked' => 'Sitzung fernabgemeldet.',
        'all_revoked' => ':name wurde von allen Geräten abgemeldet.',
        'token_revoked' => 'API-Token widerrufen.',
        'device_revoked' => 'Standort-Gerät getrennt.',
        'terminal_deactivated' => 'Terminal deaktiviert.',
    ],
];
