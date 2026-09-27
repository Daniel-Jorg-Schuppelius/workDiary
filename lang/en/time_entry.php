<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : time_entry.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'activity_type' => [
        'project' => 'Project',
        'admin' => 'Administration',
        'training' => 'Training',
        'meeting' => 'Meeting',
        'internal' => 'Internal',
        'travel' => 'Travel',
        'break' => 'Break',
        'absence' => 'Absence',
        'standby' => 'Standby',
        'other' => 'Other',
    ],
    // Projektvorschläge für offene Zeitblöcke (MVP-923).
    'suggestion' => [
        'source' => [
            'order' => 'Suggestion: order “:title”',
            'day' => 'Suggestion: today “:title”',
            'recent' => 'Suggestion: last booked',
        ],
        'book_all' => 'Book all :count suggestions',
        'booked' => ':count time blocks booked.',
    ],
];
