<?php
/*
 * Created on   : Sun May 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : archive.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    | Schwellwert in Tagen, ab dem Datensätze als archivierbar gelten.
    | Diary-Einträge werden zusätzlich nur archiviert, wenn status = -1 (erledigt).
    */
    'threshold_days' => (int) env('ARCHIVE_THRESHOLD_DAYS', 30),

    /*
    | Tageszeit (HH:MM, App-Zeitzone) für den geplanten Lauf.
    */
    'schedule_at' => env('ARCHIVE_SCHEDULE_AT', '03:00'),
];
