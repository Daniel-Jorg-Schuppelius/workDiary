<?php
/*
 * Created on   : Mon May 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sickness.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Ab dem wievielten Kalendertag ist eine Arbeitsunfähigkeitsbescheinigung
    | (AU) vom Mitarbeiter beizubringen? Per Default DE: 4. Tag.
    |--------------------------------------------------------------------------
    */
    'attachment_required_from_day' => (int) env('SICKNESS_ATTACHMENT_FROM_DAY', 4),

    /*
    |--------------------------------------------------------------------------
    | Entgeltfortzahlungs-Anspruch (§ 3 EntgFG): in Wochen.
    |--------------------------------------------------------------------------
    */
    'continued_pay_weeks' => (int) env('SICKNESS_CONTINUED_PAY_WEEKS', 6),

    /*
    |--------------------------------------------------------------------------
    | Reset-Frist für die Lohnfortzahlungs-Kette: Nach wie vielen Monaten
    | ohne Arbeitsunfähigkeit derselben Erkrankung beginnt der Anspruch neu?
    |--------------------------------------------------------------------------
    */
    'chain_reset_after_months' => (int) env('SICKNESS_CHAIN_RESET_MONTHS', 6),

    /*
    |--------------------------------------------------------------------------
    | Auto-Approve: Krankmeldungen sind keine Genehmigungsprozesse. Sobald
    | erfasst, gelten sie als reportet.
    |--------------------------------------------------------------------------
    */
    'auto_approve' => (bool) env('SICKNESS_AUTO_APPROVE', true),

    /*
    |--------------------------------------------------------------------------
    | Speicherort & Größenlimit für AU-Bescheinigungen.
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'disk' => env('SICKNESS_ATTACHMENT_DISK', 'local'),
        'path' => 'sick-notes',
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'heic'],
        'max_kilobytes' => (int) env('SICKNESS_ATTACHMENT_MAX_KB', 10240),
    ],
];
