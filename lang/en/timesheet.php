<?php

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
