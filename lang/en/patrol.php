<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Patrol report',
        'subtitle' => 'Patrols per route and site in the period — expected, on time, late and missed.',
        'expected' => 'Expected checkpoints',
        'onTime' => 'on time',
        'late' => 'late',
        'missed' => 'missed',
        'note' => 'Reason',
        'runs' => 'Patrols',
        'punctuality' => 'Punctuality',
        'aborted' => 'aborted',
        'allRoutes' => 'All routes',
        'allSites' => 'All sites',
        'empty' => 'No patrols in this period.',
        'summary' => ':runs patrols, :completed completed and :aborted aborted',
    ],
    'scan' => [
        'implausibleTime' => 'The device scan time lies outside the patrol — please check the device clock.',
    ],
];
