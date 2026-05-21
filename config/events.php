<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : events.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Reminder Offsets (Minuten vor Event-Start)
    |--------------------------------------------------------------------------
    | Wird verwendet, wenn weder das Event noch die Kategorie eigene
    | Offsets definieren. Werte in absteigender Reihenfolge halten.
    */
    'reminder_offsets' => [
        10080, // 7 Tage
        1440,  //  1 Tag
        60,    //  1 Stunde
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    | Standardkanäle für Event-Erinnerungen. Einzelne Reminder können
    | per Spalte 'channel' überschreiben.
    */
    'channels' => [
        'mail',
        'webpush',
        'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurrence Materialization Window
    |--------------------------------------------------------------------------
    | Tage in der Zukunft, für die Serien-Vorkommen (occurrences) als
    | konkrete Event-Datensätze persistiert werden.
    */
    'materialization_days' => 90,

    /*
    |--------------------------------------------------------------------------
    | Raumkonflikte
    |--------------------------------------------------------------------------
    | 'hard'  = Raumdoppelbelegung wird im Service strikt geblockt.
    | 'warn'  = Konflikt wird nur als Warnung erfasst.
    */
    'room_conflict_mode' => 'hard',

    /*
    |--------------------------------------------------------------------------
    | Zertifikate
    |--------------------------------------------------------------------------
    */
    'certificate' => [
        'default_valid_months' => 12,
        // Tage vor Ablauf, ab denen Erinnerungen ausgelöst werden.
        'expiry_warning_days' => [60, 30, 7],
    ],

    /*
    |--------------------------------------------------------------------------
    | ICS-Feed
    |--------------------------------------------------------------------------
    */
    'ics' => [
        'product_id' => '-//workDiary//Events//DE',
        'calendar_name' => 'workDiary Events',
    ],
];
