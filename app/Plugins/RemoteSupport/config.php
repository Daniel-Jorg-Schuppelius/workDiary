<?php
/*
 * Created on   : Mon Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Fernwartung (AnyDesk, TeamViewer, RustDesk, Splashtop, pcvisit).
 | Verbindungs-Reports werden über die am Asset hinterlegte Geräte-ID dem
 | Kunden-Standardprojekt als TimeEntry zugeordnet. Eingehängt vom
 | RemoteSupportServiceProvider unter `plugins.remote-support`. ENV dient nur
 | als Fallback für Konsolen-/Test-Kontexte; die Anbieter und ihre Schlüssel
 | nennt App\Plugins\RemoteSupport\Providers\RemoteProviders.
 */
return [
    'enabled' => env('REMOTE_SUPPORT_ENABLED', false),
    // Wie viele Tage rückwirkend pro Sync-Lauf abgefragt werden.
    'sync_window_days' => (int) env('REMOTE_SUPPORT_SYNC_WINDOW_DAYS', 2),
    // Importierte Sessions als abrechenbar markieren?
    'default_billable' => (bool) env('REMOTE_SUPPORT_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
    'default_user_id' => env('REMOTE_SUPPORT_DEFAULT_USER_ID'),

    'anydesk' => [
        'enabled' => env('ANYDESK_ENABLED', false),
        'license_id' => env('ANYDESK_LICENSE_ID'),
        'api_key' => env('ANYDESK_API_KEY'),
        'base_url' => env('ANYDESK_BASE_URL', 'https://v1.api.anydesk.com:8081'),
    ],

    // Verbindungsversuche (0-Sekunden-Datensätze) bis zu N Minuten vor einer
    // Sitzung gelten als deren Tätigkeitsbeginn und ziehen den Buchungsstart vor.
    'attempt_lead_minutes' => (int) env('REMOTE_SUPPORT_ATTEMPT_LEAD_MINUTES', 15),

    // Deckel für Zeit-Kürzel in Sitzungsnotizen („+1h", „2h extra"), die den
    // Buchungsbeginn vorziehen — schützt vor Tippfehlern wie „+100h".
    'note_extra_max_minutes' => (int) env('REMOTE_SUPPORT_NOTE_EXTRA_MAX_MINUTES', 480),

    // Beginnt eine Verbindung desselben Geräts höchstens N Minuten nach dem
    // Ende eines Fernwartungs-Eintrags, verlängert sie ihn (Reconnects,
    // Wartezeit bei Updates) statt einen neuen anzulegen; 0 = aus.
    'merge_gap_minutes' => (int) env('REMOTE_SUPPORT_MERGE_GAP_MINUTES', 30),

    'teamviewer' => [
        'enabled' => env('TEAMVIEWER_ENABLED', false),
        'api_key' => env('TEAMVIEWER_API_KEY'),
        'base_url' => env('TEAMVIEWER_BASE_URL', 'https://webapi.teamviewer.com/api/v1'),
    ],

    // Der kostenlose RustDesk-Server speichert keine Verbindungen; Sitzungen
    // liefert Server Pro (flavor "pro", API-Token) oder der Open-Source-
    // API-Server rustdesk-api (flavor "api_server", Anmeldung je Lauf).
    'rustdesk' => [
        'enabled' => env('RUSTDESK_ENABLED', false),
        'flavor' => env('RUSTDESK_FLAVOR', 'pro'),
        'base_url' => env('RUSTDESK_BASE_URL'),
        'api_token' => env('RUSTDESK_API_TOKEN'),
        'username' => env('RUSTDESK_USERNAME'),
        'password' => env('RUSTDESK_PASSWORD'),
        // Serverzeitzone des Open-Source-API-Servers (created_at ohne Zone); leer = Organisation.
        'timezone' => env('RUSTDESK_TIMEZONE'),
    ],

    // Splashtop Open API (nur Splashtop Enterprise); Region bestimmt den Server.
    'splashtop' => [
        'enabled' => env('SPLASHTOP_ENABLED', false),
        'region' => env('SPLASHTOP_REGION', 'eu'),
        'api_token' => env('SPLASHTOP_API_TOKEN'),
        'team_id' => env('SPLASHTOP_TEAM_ID'),
        'service_kinds' => env('SPLASHTOP_SERVICE_KINDS', 'unattended_remote,attended_remote'),
    ],

    // pcvisit GraphQL: der Integrator-Token gehört dem Betreiber (vergibt
    // pcvisit an den Softwarehersteller), der Nutzer-Token der Organisation.
    'pcvisit' => [
        'enabled' => env('PCVISIT_ENABLED', false),
        'user_token' => env('PCVISIT_USER_TOKEN'),
        'integrator_token' => env('PCVISIT_INTEGRATOR_TOKEN'),
        'endpoint' => env('PCVISIT_ENDPOINT', 'https://app.pcvisit.de/graphql'),
    ],
];
