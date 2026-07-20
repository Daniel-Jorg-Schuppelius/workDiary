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
 | Fernwartung (AnyDesk + TeamViewer). Verbindungs-Reports werden über die am
 | Asset hinterlegte Geräte-ID dem Kunden-Standardprojekt als TimeEntry
 | zugeordnet. Eingehängt vom RemoteSupportServiceProvider unter
 | `plugins.remote-support`. ENV dient nur als Fallback für Konsolen-/Test-Kontexte.
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
    'teamviewer' => [
        'enabled' => env('TEAMVIEWER_ENABLED', false),
        'api_key' => env('TEAMVIEWER_API_KEY'),
        'base_url' => env('TEAMVIEWER_BASE_URL', 'https://webapi.teamviewer.com/api/v1'),
    ],
];
