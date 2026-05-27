<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : plugins.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Plugins\Lexoffice\LexofficePlugin;
use App\Plugins\RemoteSupport\RemoteSupportPlugin;
use App\Plugins\Toggl\TogglPlugin;

return [
    /*
    |--------------------------------------------------------------------------
    | Registered plugin classes
    |--------------------------------------------------------------------------
    |
    | Each entry must be a fully qualified class name implementing
    | App\Plugins\Contracts\Plugin. Wird IMMER geladen — Aktivierung pro
    | Organisation erfolgt über die plugin_settings-Tabelle (s. /admin/plugins).
    | ENV-Variablen wie LEXOFFICE_API_KEY dienen nur noch als Fallback für
    | Tests/Konsolen-Kontexte ohne UI-Konfiguration.
    */
    'classes' => [
        LexofficePlugin::class,
        RemoteSupportPlugin::class,
        TogglPlugin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Disable
    |--------------------------------------------------------------------------
    |
    | Anzahl aufeinanderfolgender Boot-/Runtime-/Healthcheck-Fehler eines
    | Plugins, ab der das Plugin global stillgelegt wird (Setzen von
    | plugin_states.disabled_reason). 0 = nie automatisch deaktivieren.
    */
    'auto_disable_threshold' => (int) env('PLUGINS_AUTO_DISABLE_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Per-plugin configuration
    |--------------------------------------------------------------------------
    */
    'lexoffice' => [
        'enabled' => env('LEXOFFICE_ENABLED', false),
        'api_key' => env('LEXOFFICE_API_KEY'),
        'base_url' => env('LEXOFFICE_BASE_URL', 'https://api.lexoffice.io/v1'),
        // Default values applied to vouchers/contacts when not set on the model
        'default_currency' => env('LEXOFFICE_DEFAULT_CURRENCY', 'EUR'),
        'default_tax_type' => env('LEXOFFICE_DEFAULT_TAX_TYPE', 'net'), // net|gross
        'default_vat_rate' => (float) env('LEXOFFICE_DEFAULT_VAT_RATE', 19.0),
        // Strategie bei Konflikten zwischen Remote- und Local-Stand beim Pull-Sync.
        // Werte: lexoffice_wins | local_wins | manual_review (siehe LexofficeMatchPolicy).
        'match_policy' => env('LEXOFFICE_MATCH_POLICY', 'manual_review'),
        // Soll der Pull-Sync remote Kontakte ohne lokales Pendant lokal neu anlegen?
        'create_missing_local' => (bool) env('LEXOFFICE_CREATE_MISSING_LOCAL', false),
    ],

    /*
    | Fernwartung (AnyDesk + TeamViewer). Verbindungs-Reports werden über die
    | am Asset hinterlegte Geräte-ID dem Kunden-Standardprojekt als TimeEntry
    | zugeordnet. ENV dient nur als Fallback für Konsolen-/Test-Kontexte.
    */
    'remote-support' => [
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
            'base_url' => env('ANYDESK_BASE_URL', 'https://v1.api.anydesk.com'),
        ],
        'teamviewer' => [
            'enabled' => env('TEAMVIEWER_ENABLED', false),
            'api_key' => env('TEAMVIEWER_API_KEY'),
            'base_url' => env('TEAMVIEWER_BASE_URL', 'https://webapi.teamviewer.com/api/v1'),
        ],
    ],

    /*
    | Toggl Track. Importiert Projekt-/Zeitdaten per API (v9) oder Detailed-Report-CSV.
    | Toggl-Clients werden auf Kunden, Toggl-Projekte auf Projekte gematcht; nicht
    | Zuordenbares landet in der Toggl-Inbox. ENV dient nur als Fallback.
    */
    'toggl' => [
        'enabled' => env('TOGGL_ENABLED', false),
        'api_token' => env('TOGGL_API_TOKEN'),
        'base_url' => env('TOGGL_BASE_URL', 'https://api.track.toggl.com/api/v9'),
        // Optionaler Workspace-Filter; leer = alle Workspaces des Tokens.
        'workspace_id' => env('TOGGL_WORKSPACE_ID'),
        // Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.
        'sync_window_days' => (int) env('TOGGL_SYNC_WINDOW_DAYS', 30),
        // Wenn false, werden importierte Zeiten nie als abrechenbar markiert.
        'default_billable' => (bool) env('TOGGL_DEFAULT_BILLABLE', true),
        // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
        'default_user_id' => env('TOGGL_DEFAULT_USER_ID'),
    ],
];
