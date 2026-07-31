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
 | Toggl Track. Importiert Projekt-/Zeitdaten per API (v9) oder Detailed-Report-CSV.
 | Toggl-Clients werden auf Kunden, Toggl-Projekte auf Projekte gematcht; nicht
 | Zuordenbares landet in der Toggl-Inbox. Eingehängt vom TogglServiceProvider
 | unter `plugins.toggl`. ENV dient nur als Fallback für Tests/Konsolen-Kontexte.
 */
return [
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
    // Spiegelung workDiary → Toggl (lokal erfasste Zeiten anlegen); bewusst aus.
    'export_enabled' => (bool) env('TOGGL_EXPORT_ENABLED', false),
];
