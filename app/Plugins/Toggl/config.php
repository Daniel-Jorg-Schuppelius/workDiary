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
 | Toggl Track bzw. neues Toggl. Importiert Projekt-/Zeitdaten per API oder Detailed-Report-CSV.
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
    // Quelle (track|focus): genau ein Toggl-Produkt wird gelesen und beschrieben.
    'source' => env('TOGGL_SOURCE', 'track'),
    // Neues Toggl (vormals Toggl 2.0 / Focus): API-Schlüssel toggl_sk_…,
    // Organisation und Workspace optional (leer = über den Kontodienst ermitteln).
    'focus_api_key' => env('TOGGL_FOCUS_API_KEY'),
    'focus_base_url' => env('TOGGL_FOCUS_BASE_URL', 'https://focus.toggl.com/api'),
    'accounts_url' => env('TOGGL_ACCOUNTS_URL', 'https://accounts.toggl.com'),
    'focus_organization_id' => env('TOGGL_FOCUS_ORGANIZATION_ID'),
    'focus_workspace_id' => env('TOGGL_FOCUS_WORKSPACE_ID'),
    // Toggl-Tarif (free|starter|premium|enterprise) → Drosselung passend zur
    // Stunden-Quota; leer = nur Burst-Schutz (1 Request/s).
    'api_plan' => env('TOGGL_API_PLAN'),
    // Mindestabstand (Sekunden) zwischen API-Aufrufen; überschreibt den Tarif-Wert.
    'request_interval' => env('TOGGL_REQUEST_INTERVAL'),
    // Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.
    'sync_window_days' => (int) env('TOGGL_SYNC_WINDOW_DAYS', 30),
    // Wenn false, werden importierte Zeiten nie als abrechenbar markiert.
    'default_billable' => (bool) env('TOGGL_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
    // Greift nur im Einbenutzer-Modus oder bei ausdrücklicher Auswahl beim Buchen (MVP-509).
    'default_user_id' => env('TOGGL_DEFAULT_USER_ID'),
    // Einbenutzer-Modus (MVP-509): Einträge ohne zuordenbaren Toggl-Benutzer auf den
    // Standard-Benutzer buchen. Aus = offener Zuordnungsfall in der Integrations-Inbox.
    'single_user_mode' => (bool) env('TOGGL_SINGLE_USER_MODE', false),
    // Spiegelung workDiary → Toggl (lokal erfasste Zeiten anlegen); bewusst aus.
    'export_enabled' => (bool) env('TOGGL_EXPORT_ENABLED', false),
];
