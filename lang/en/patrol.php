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
    // Maintenance and deviations (MVP-1611).
    'route' => [
        'edit_title' => 'Edit route',
        'responsible' => 'Responsible for deviations',
        'responsible_hint' => 'Receives the open item for deviations. Empty: the team lead of the person doing the patrol.',
        'updated' => 'Route saved.',
        'deactivate_confirm' => 'Deactivate the route? A running patrol may finish, new ones cannot be started.',
        'deactivated' => 'Route deactivated — it can no longer be started.',
        'activated' => 'Route is active again.',
    ],
    'checkpoint' => [
        'edit_title' => 'Edit checkpoint',
        'edit_hint' => 'Changes apply to future scans; recorded scans keep their rating.',
        'updated' => 'Checkpoint “:label” saved.',
        'deactivate_confirm' => 'Deactivate the checkpoint? From now on it is no longer expected and can no longer be scanned; earlier patrols stay unchanged.',
        'deactivated_done' => 'Checkpoint “:label” deactivated.',
        'deactivated' => 'This checkpoint is deactivated.',
        'inactive_badge' => 'deactivated',
    ],
    'deviation' => [
        'running_title' => 'Patrol “:route” deviates',
        'late_line' => '“:label” outside the window (:delta min)',
        'missed_line' => ':count due checkpoints without a scan',
        'overdue_title' => 'Patrol “:route” not finished',
        'overdue_line' => 'Planned duration of :minutes minutes exceeded (start :start).',
    ],
];
