<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : backup.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Backup-Heartbeat-Token (MVP-046)
    |--------------------------------------------------------------------------
    |
    | Bearer-Token, das von `scripts/backup.sh` (bzw. einem äquivalenten
    | externen Tool) beim Aufruf von POST /admin/backup/heartbeat
    | mitgegeben werden muss. Wird durch `php artisan workdiary:backup:rotate-token`
    | rotiert (schreibt direkt in .env).
    */
    'heartbeat_token' => env('BACKUP_HEARTBEAT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Schwellen für Diagnose-Seite (§3.7)
    |--------------------------------------------------------------------------
    |
    | Alter des letzten Heartbeats in Stunden. Über `warn` → DiagnosticStatus::Warn,
    | über `critical` → DiagnosticStatus::Critical.
    */
    'thresholds_hours' => [
        'warn' => (int) env('BACKUP_HEARTBEAT_WARN_HOURS', 26),
        'critical' => (int) env('BACKUP_HEARTBEAT_CRITICAL_HOURS', 72),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plausibilität für `workdiary:backup:check-restore` (MVP-046 §6)
    |--------------------------------------------------------------------------
    |
    | `min_size_bytes` — falls > 0, wird ein Heartbeat unter dieser Größe
    | als Warn gemeldet (z. B. abgebrochenes Backup).
    | `size_drop_ratio` — Warn-Schwelle relativ zum Median der letzten
    | sieben Heartbeats; 0.5 = "kleiner als die Hälfte des Medians".
    */
    'min_size_bytes' => (int) env('BACKUP_MIN_SIZE_BYTES', 0),
    'size_drop_ratio' => (float) env('BACKUP_SIZE_DROP_RATIO', 0.5),

    /*
    |--------------------------------------------------------------------------
    | Erwartetes Heartbeat-Intervall der Statusseite (Feature 017)
    |--------------------------------------------------------------------------
    |
    | Frische-Schwelle für die Admin-Backup-Statusseite je Quelle. Ist der
    | jüngste Heartbeat einer Quelle älter als `heartbeat_freshness_hours`
    | (Default 26 h), zeigt die Seite eine rote „überfällig"-Markierung; gibt
    | es gar keinen Heartbeat, „kein Backup registriert".
    |
    | Bewusst plattform-/serverweit (config, NICHT organizations.settings): der
    | Backup-Heartbeat (backup_heartbeats) wird vom externen Backup-Job ohne
    | Tenant-Kontext gepostet und gehört nicht zur Mandantengrenze.
    */
    'heartbeat_freshness_hours' => (int) env('BACKUP_HEARTBEAT_FRESHNESS_HOURS', 26),

    /*
    |--------------------------------------------------------------------------
    | Überfälligkeit des Restore-Tests (Feature 017, §6.3)
    |--------------------------------------------------------------------------
    |
    | Liegt der jüngste ERFOLGREICHE Restore-Test (result = passed) länger als
    | `restore_test_overdue_days` (Default 180) zurück oder fehlt vollständig,
    | warnt die Statusseite „überfälliger Restore-Test".
    */
    'restore_test_overdue_days' => (int) env('BACKUP_RESTORE_TEST_OVERDUE_DAYS', 180),
];
