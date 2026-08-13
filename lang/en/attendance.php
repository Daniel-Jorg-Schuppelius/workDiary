<?php

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
    ],
    'correction' => [
        'action' => [
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
        ],
    ],
];
