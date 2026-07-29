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
 | OpenProject (https://www.openproject.org). Bidirektionale Anbindung an die
 | API v3 (HAL+JSON, Basic-Auth „apikey:<token>"). Diese Datei liefert die
 | ENV-Fallbacks/Defaults des Plugins; sie wird vom OpenProjectServiceProvider
 | per mergeConfigFrom unter `plugins.openproject` eingehängt. Die echte
 | Konfiguration kommt pro Organisation aus plugin_settings (verschlüsselt);
 | ENV dient nur als Fallback für Tests/Konsolen-Kontexte ohne UI-Konfiguration.
 */
return [
    'enabled' => env('OPENPROJECT_ENABLED', false),
    // Instanz-URL (mit oder ohne /api/v3 — wird normalisiert), z. B. https://op.example.com
    'base_url' => env('OPENPROJECT_BASE_URL'),
    'api_token' => env('OPENPROJECT_API_TOKEN'),
    // Wie viele Tage rückwirkend pro API-Lauf abgefragt werden.
    'sync_window_days' => (int) env('OPENPROJECT_SYNC_WINDOW_DAYS', 30),
    // Wenn false, werden importierte Zeiten nie als abrechenbar markiert.
    'default_billable' => (bool) env('OPENPROJECT_DEFAULT_BILLABLE', true),
    // Benutzer, dem importierte Zeiten zugeordnet werden (sonst Org-Owner / erster Benutzer).
    'default_user_id' => env('OPENPROJECT_DEFAULT_USER_ID'),
    // OpenProject-Activity-ID (TimeEntriesActivity), unter der zurückgebuchte Zeiten angelegt werden.
    'default_activity_id' => env('OPENPROJECT_DEFAULT_ACTIVITY_ID'),
    // Struktur-Sync: fehlende workDiary-Projekte/Aufgaben automatisch anlegen statt nur mappen?
    'create_missing_projects' => (bool) env('OPENPROJECT_CREATE_MISSING_PROJECTS', false),
    // Korrekturen an importierten Zeiten nach OpenProject zurückschreiben (Änderung/Löschung).
    'writeback' => (bool) env('OPENPROJECT_WRITEBACK', false),
];
