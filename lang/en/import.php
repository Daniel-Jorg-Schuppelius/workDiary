<?php

declare(strict_types=1);

return [
    'entity' => [
        'customers' => 'Customers',
        'projects' => 'Projects',
        'users' => 'Users',
        'materials' => 'Materials',
        'vehicles' => 'Vehicles',
        'scheduled_shifts' => 'Shift schedules',
        'tours' => 'Tours',
        'remote_sessions' => 'Remote support sessions',
    ],

    'state' => [
        'preflight' => 'Preflight',
        'awaitingApproval' => 'Awaiting approval',
        'running' => 'Running',
        'succeeded' => 'Succeeded',
        'partial' => 'Partial',
        'failed' => 'Failed',
    ],

    'errorCode' => [
        'required' => 'Required field missing',
        'format' => 'Format error',
        'unique' => 'Value not unique',
        'fkMissing' => 'Reference not found',
        'tooLong' => 'Value too long',
        'outOfRange' => 'Value out of range',
        'persist' => 'Persistence error',
        'headerMissing' => 'Column missing',
        'headerUnknown' => 'Column unknown',
    ],

    'error' => [
        'required' => 'Required field :field is missing.',
        'tooLong' => 'Field :field exceeds maximum length of :max characters.',
        'header' => [
            'missing' => 'Required column :column is missing in CSV header.',
            'duplicate' => 'Column :column appears multiple times.',
        ],
        'format' => [
            'default' => 'Field :field has an invalid format (:reason).',
            'email' => 'Not a valid email address.',
            'country' => 'Country code must be 2-3 uppercase letters (ISO 3166-1).',
            'currency' => 'Currency code must be 3 uppercase letters (ISO 4217).',
            'enum' => 'Value is not a valid status.',
            'parse' => 'File could not be parsed: :reason',
            'date' => 'Not a valid date (expected e.g. "28.05.2026, 09:42:09").',
            'time' => 'Not a valid time (expected HH:MM).',
            'status' => 'Value is not a valid status.',
        ],
        'outOfRange' => [
            'rowLimit' => 'Row limit (:max) exceeded — remainder ignored.',
        ],
        'fkMissing' => [
            'customer' => 'No customer with number :number found.',
            'user' => 'No user with email :value found.',
        ],
        'persist' => [
            'noBookingUser' => 'No bookable user found in the organisation.',
        ],
    ],
];
