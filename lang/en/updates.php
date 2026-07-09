<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : updates.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => ['section' => 'Available updates'],
    'field' => [
        'mode' => 'Check mode',
        'last_checked' => 'Last check',
        'component' => 'Component',
        'versions' => 'Installed → Available',
        'classification' => 'Classification',
        'requirements' => 'Preparation',
        'incompatible' => 'Incompatible with this app version',
        'changelog' => 'Changelog',
    ],
    'classification' => [
        'normal' => 'Routine',
        'recommended' => 'Recommended',
        'security' => 'Security',
        'critical' => 'Critical',
    ],
    'requires' => [
        'backup' => 'Backup required',
        'maintenance_window' => 'Maintenance window recommended',
        'migrations' => 'Database migrations',
    ],
    'action' => [
        'check_now' => 'Check now',
        'import' => 'Offline import',
        'snooze' => 'Snooze',
        'acknowledge' => 'Mute',
    ],
    'empty' => 'No pending updates known.',
    'flash' => [
        'checked' => 'Update check finished — :count pending update(s).',
        'imported' => 'Update document imported — :count pending update(s).',
        'snoozed' => 'Update notice snoozed.',
        'acknowledged' => 'Update notice muted (remains visible here).',
    ],
];
