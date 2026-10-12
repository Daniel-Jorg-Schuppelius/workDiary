<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Vergabe von Kunden- und Lieferantennummern durch ein Buchhaltungsprogramm (Phase 141, MVP-1130–1134).
return [
    'source' => [
        'local' => 'workDiary',
        'inactive' => ':program (inactive – workDiary assigns)',
        'column' => 'Assigned by',
        'page_hint' => 'If an accounting program assigns customer or supplier numbers, a new record receives its number when it is transferred. Existing numbers stay unchanged. Invoice numbers are set in the program\'s settings.',
    ],
    'field' => [
        'locked_hint' => ':program assigns the number on transfer.',
    ],
    'flash' => [
        'issued' => 'Number :number taken over from :program.',
        'pending' => 'The number from :program follows with the next transfer: :reason',
        'conflict' => 'Number :number from :program is already assigned to :holder. You will find the case in the matching inbox.',
    ],
    'conflict' => [
        'title' => 'Number :number from :program is already assigned to :holder',
    ],
    'error' => [
        'taken_remote' => 'Number :number is already assigned to “:name” in :program.',
        'taken_local' => 'Number :number is already assigned to :holder. Please change the number there first.',
        'no_number' => ':program did not return a number.',
    ],
];
