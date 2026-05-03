<?php

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
