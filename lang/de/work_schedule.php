<?php
/*
 * Created on   : Thu Jun 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work_schedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'type' => [
        'flextime' => 'Gleitzeit',
        'weekly' => 'Feste Wochenarbeitszeit',
        'per_weekday' => 'Wochentagsweise',
        'trust' => 'Vertrauensarbeitszeit',
    ],
    'type_hint' => [
        'flextime' => 'Einheitliches Tagessoll an Arbeitstagen, mit Kern- und Rahmenzeit.',
        'weekly' => 'Nur ein Wochensoll, frei über die Woche verteilbar.',
        'per_weekday' => 'Pro Wochentag individuelle Stunden oder feste Von–bis-Zeiten.',
        'trust' => 'Keine Soll-Erfassung – es wird nur die Ist-Anwesenheit erfasst.',
    ],
];
