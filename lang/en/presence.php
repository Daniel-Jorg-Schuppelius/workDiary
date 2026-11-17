<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : presence.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Selbstauskunft am Anwesenheits-Board (Feature 103, MVP-1669).
return [
    'board' => 'Current attendance',
    'kind' => [
        'do_not_disturb' => 'Do not disturb',
        'brief_away' => 'Briefly away',
        'out_of_office' => 'Out of the office',
    ],
    'until' => ':kind until :time',
    'validation' => [
        'until_required' => 'This status needs an end.',
        'until_range' => 'The end must be in the future and at most :hours hours away.',
    ],
    'dialog' => [
        'title' => 'My status',
        'submit' => 'Set status',
        'hint' => 'Only your current status is shown – without a reason and without history. It ends at its end time, when you clock out or when you reset it.',
        'current' => 'Current',
        'kind' => 'Status',
        'duration' => 'For',
        'no_duration' => 'No end (until reset or clock-out)',
        'until' => 'Or exactly until',
        'until_hint' => 'Takes precedence over “For”. “Briefly away” and “Out of the office” need an end.',
    ],
    'duration' => [
        '15' => '15 minutes',
        '30' => '30 minutes',
        '60' => '1 hour',
        '120' => '2 hours',
        '240' => '4 hours',
        '480' => '8 hours',
    ],
    'action' => [
        'set' => 'My status',
        'clear' => 'Reset status',
    ],
    'flash' => [
        'set' => 'Your status “:status” is set.',
        'cleared' => 'Your status has been reset.',
    ],
];
