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
];
