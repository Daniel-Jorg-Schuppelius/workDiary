<?php

return [
    'vehicle' => [
        'type' => [
            'car' => 'Car',
            'van' => 'Van',
            'truck' => 'Truck',
            'bicycle' => 'Bicycle',
            'other' => 'Other',
        ],
        'propulsion' => [
            'diesel' => 'Diesel',
            'petrol' => 'Petrol',
            'gas' => 'Gas',
            'hybrid' => 'Hybrid',
            'electric' => 'Electric',
            'muscle' => 'Muscle power',
            'other' => 'Other',
        ],
        'ownership' => [
            'owned' => 'Owned',
            'leased' => 'Leased',
            'rental' => 'Rental',
        ],
    ],
    'sickness' => [
        'kind' => [
            'initial' => 'Initial certificate',
            'follow_up' => 'Follow-up certificate',
        ],
    ],
    'tour' => [
        'status' => [
            'draft' => 'Draft',
            'planned' => 'Planned',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
    ],
    'activity' => [
        'category_type' => [
            'admin' => 'Administration',
            'training' => 'Training',
            'meeting' => 'Meeting',
            'internal' => 'Internal',
            'travel' => 'Travel',
            'break' => 'Break',
            'absence' => 'Absence',
            'standby' => 'Standby',
            'other' => 'Other',
        ],
    ],
    'vacation' => [
        'type' => [
            'vacation' => 'Vacation',
            'sick' => 'Sick',
            'special' => 'Special leave',
            'unpaid' => 'Unpaid',
        ],
        'status' => [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ],
    ],
    'project' => [
        'status' => [
            'active' => 'Active',
            'paused' => 'Paused',
            'archived' => 'Archived',
        ],
    ],
    'task' => [
        'status' => [
            'open' => 'Open',
            'in_progress' => 'In progress',
            'done' => 'Done',
        ],
        'priority' => [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ],
    ],
    'timesheet' => [
        'status' => [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'signed' => 'Signed',
            'locked' => 'Locked',
        ],
        'kind' => [
            'project' => 'Project',
            'personal_day' => 'Personal day',
        ],
    ],
    'time_entry' => [
        'kind' => [
            'work' => 'Work',
            'travel' => 'Travel',
            'standby' => 'Standby',
        ],
    ],
];
