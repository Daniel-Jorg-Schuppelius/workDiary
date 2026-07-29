<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 | Kimai-Import/-Export (CSV + REST-API). Importiert Zeiteinträge aus einem
 | Kimai-Timesheet-CSV-Export oder direkt über die Kimai-2.x-API (Bearer-Token);
 | optional werden in workDiary erfasste Zeiten als Kimai-Timesheets
 | zurückgebucht. Kimai-Kunden/-Projekte werden auf Kunden/Projekte gematcht,
 | nicht Zuordenbares landet in der universellen Zuordnungs-Inbox. Eingehängt
 | vom KimaiServiceProvider unter `plugins.kimai`. ENV nur als Fallback
 | (Tests/Konsole).
 */
return [
    'enabled' => env('KIMAI_ENABLED', false),
    // Wenn false, werden importierte Zeiten nie als abrechenbar markiert.
    'default_billable' => (bool) env('KIMAI_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
    'default_user_id' => env('KIMAI_DEFAULT_USER_ID'),
    // Basis-URL der Kimai-Instanz (z. B. https://kimai.example.com) — ohne /api.
    'base_url' => env('KIMAI_BASE_URL'),
    // API-Token des Kimai-Benutzers (Kimai 2.x: Profil → API-Zugang).
    'api_token' => env('KIMAI_API_TOKEN'),
    // user=all abfragen (braucht in Kimai das Recht view_other_timesheet).
    'api_all_users' => (bool) env('KIMAI_API_ALL_USERS', true),
    // Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.
    'sync_window_days' => (int) env('KIMAI_SYNC_WINDOW_DAYS', 30),
    // Kimai-Activity-ID, unter der zurückgebuchte Zeiten angelegt werden (Pflicht für Export).
    'default_activity_id' => env('KIMAI_DEFAULT_ACTIVITY_ID'),
    // Rückbuchung workDiary → Kimai aktivieren.
    'export_enabled' => (bool) env('KIMAI_EXPORT_ENABLED', false),
    // Korrekturen an IMPORTIERTEN Zeiten nach Kimai zurückschreiben (Änderung/Löschung).
    'writeback' => (bool) env('KIMAI_WRITEBACK', false),
];
