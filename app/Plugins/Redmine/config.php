<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : config.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Redmine-Aufgaben- und Zeitimport (Feature 172, Phase 158). REST-API mit
 * API-Schlüssel im Header X-Redmine-API-Key; Seiten über offset/limit mit
 * total_count. Basis-URL und Schlüssel je Organisation in den
 * Plugin-Einstellungen; interne Adressen nur mit dem Opt-in.
 */
return [
    'enabled' => env('REDMINE_ENABLED', false),

    // Seitengröße (Redmine-Maximum: 100) und Seitenlimit je Zuordnung und Lauf.
    'page_size' => (int) env('REDMINE_PAGE_SIZE', 100),
    'max_pages' => (int) env('REDMINE_MAX_PAGES', 100),

    // Zeitfenster des Zeitimports in Tagen (rollierend, je Lauf vollständig gelesen).
    'time_window_days' => (int) env('REDMINE_TIME_WINDOW_DAYS', 30),
];
