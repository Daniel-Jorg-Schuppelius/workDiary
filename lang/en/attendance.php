<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : attendance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Intermediate statuses (MVP-532): home office/errand.
    'intermediate' => [
        'homeoffice' => 'Home office',
        'errand' => 'Errand',
        'start_homeoffice' => 'Start home office',
        'end_homeoffice' => 'End home office',
        'start_errand' => 'Start errand',
        'end_errand' => 'End errand',
    ],
    'status' => [
        'open' => 'Open',
        'closed' => 'Closed',
        'auto_closed' => 'Auto-closed',
        'adjusted' => 'Adjusted',
        'cancelled' => 'Cancelled',
    ],
    'source' => [
        'clock' => 'Clock',
        'manual' => 'Manual',
        'import' => 'Import',
        'auto_close' => 'Auto close',
        'terminal' => 'Terminal',
        'phone' => 'Phone',
        'learning' => 'Learning time',
    ],
    'correction' => [
        'action' => [
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ],
    ],
    'error' => [
        'target_day_locked' => 'The target day is closed or the month released — please request a time correction.',
        'duration_too_long' => 'A clocking must not exceed :hours hours.',
    ],
];
