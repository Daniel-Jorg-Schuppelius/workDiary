<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Clockify-Import (CSV + REST-API). Importiert Zeiteinträge aus einem
 | Clockify-Detailed-Report-CSV oder über die Reports-API (X-Api-Key);
 | Clients/Projekte werden auf Kunden/Projekte gematcht, nicht Zuordenbares
 | landet in der universellen Zuordnungs-Inbox. Eingehängt vom
 | ClockifyServiceProvider unter `plugins.clockify`. ENV nur als Fallback
 | (Tests/Konsole). Free-Plan: 30 API-Requests/h → CSV-Weg bewerben.
 */
return [
    'enabled' => env('CLOCKIFY_ENABLED', false),
    // Wenn false, werden importierte Zeiten nie als abrechenbar markiert.
    'default_billable' => (bool) env('CLOCKIFY_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
    'default_user_id' => env('CLOCKIFY_DEFAULT_USER_ID'),
    // Einbenutzer-Modus (MVP-509): Einträge ohne zuordenbaren Quell-Benutzer auf den Standard-Benutzer buchen.
    'single_user_mode' => (bool) env('CLOCKIFY_SINGLE_USER_MODE', false),
    // API-Key (Clockify → Profil → Advanced → API).
    'api_key' => env('CLOCKIFY_API_KEY'),
    // Workspace-ID (leer = Standard-Workspace des API-Keys).
    'workspace_id' => env('CLOCKIFY_WORKSPACE_ID'),
    // Regionale Instanzen abweichend (z. B. https://euc1.api.clockify.me/api).
    'base_url' => env('CLOCKIFY_BASE_URL'),
    'reports_base_url' => env('CLOCKIFY_REPORTS_BASE_URL'),
    // Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.
    'sync_window_days' => (int) env('CLOCKIFY_SYNC_WINDOW_DAYS', 30),
    // Korrekturen an importierten Zeiten nach Clockify zurückschreiben (Änderung/Löschung).
    'writeback' => (bool) env('CLOCKIFY_WRITEBACK', false),
];
