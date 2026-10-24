<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : redmine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Imports issues of selected Redmine projects as tasks into WorkDiary projects and, if desired, the time entries as time bookings, read-only: title, description, status via “closed” and due date. Conflicts and cases to review go to the integration inbox.',
    ],
    'source' => 'Instance',
    'task_link' => 'Open in Redmine',
    'health' => [
        'not_configured' => 'Redmine is not set up (base URL or API key missing).',
        'not_connected' => 'Redmine connection not checked yet.',
    ],
    'settings' => [
        'base_url' => 'Base URL',
        'base_url_help' => 'Address of the Redmine instance, e.g. https://redmine.example.com.',
        'api_key' => 'API key',
        'api_key_help' => 'In Redmine under My account → API access key; the REST API must be enabled in the administration. The account needs a role in the projects that may view issues and spent time, with visibility “all”.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an instance in your own network: allows a base URL with a private or internal address. Logged, and only effective if the operator permits it.',
        'default_billable' => 'Import times as billable',
        'default_billable_help' => 'If off, imported times are never billable; if on, the target project’s setting applies.',
        'time_window_days' => 'Time window (days)',
        'time_window_days_help' => 'How many days back each run reads the time entries. Older entries stay untouched.',
    ],
    'time' => [
        'fallback' => 'Redmine time entry',
    ],
    'flash' => [
        'times_enabled' => 'Time import enabled for this mapping.',
        'times_disabled' => 'Time import disabled for this mapping. Times already imported are kept.',
        'user_invalid' => 'Please choose a user of your organisation.',
        'user_mapped' => 'User mapped. The next sync books the waiting times.',
        'user_unmapped' => 'Mapping removed. Times already booked are kept.',
    ],
    'times' => [
        'title' => 'Time entries',
        'hint' => 'For each mapping, the Redmine time entries of the last :days days can be imported. Redmine only knows day and hours, so they are booked as a duration without clock time in the target project; the task is the one of the linked issue.',
        'col' => [
            'project' => 'Redmine project',
            'last_run' => 'Last time import',
            'import' => 'Import times',
        ],
        'counters' => ':created new · :updated changed · :unresolved_users waiting for a user · :removed removed',
        'enabled' => 'On',
        'disabled' => 'Off',
        'empty' => 'No project mapping yet',
    ],
    'users' => [
        'title' => 'User mapping',
        'hint' => 'Time entries are only booked for mapped Redmine users, never silently for someone else. The next sync books waiting entries after the mapping.',
        'waiting' => ':name (:count waiting entries)',
        'map' => 'Map',
        'none_pending' => 'No time entries are waiting for a user mapping',
        'mapped' => 'Mapped',
        'mapping' => 'Redmine user #:redmine → :user',
        'unmap' => 'Remove mapping',
        'confirm_unmap' => 'Remove mapping? Times already booked are kept, new entries wait again.',
    ],
];
