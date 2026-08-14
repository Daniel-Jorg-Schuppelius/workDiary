<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : timesheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'titles' => [
        'index' => 'Timesheet',
        'show' => 'Timesheet #:id',
    ],
    'fields' => [
        'date' => 'Date',
        'project' => 'Project',
        'user' => 'Employee',
        'status' => 'Status',
        'started_at' => 'Start',
        'ended_at' => 'End',
        'break_minutes' => 'Break (min)',
        'duration' => 'Duration',
        'kind' => 'Kind',
        'description' => 'Description',
        'notes' => 'Notes',
    ],
    'totals' => [
        'work' => 'Total work',
        'break' => 'Total break',
        'material_net' => 'Total material (net)',
    ],
    'sections' => [
        'entries' => 'Time entries',
        'materials' => 'Materials',
        'customer_release' => 'Customer release',
        'notes' => 'Notes',
    ],
    'signature' => [
        'signed_at' => 'Signed at :datetime',
        'ip' => 'IP :ip',
        'hash' => 'SHA-256: :hash',
        'none' => '— no signature —',
    ],
];
