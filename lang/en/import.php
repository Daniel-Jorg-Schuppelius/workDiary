<?php

declare(strict_types=1);

return [
    'entity' => [
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'articles' => 'Articles',
        'projects' => 'Projects',
        'users' => 'Users',
        'materials' => 'Materials',
        'vehicles' => 'Vehicles',
        'scheduled_shifts' => 'Shift schedules',
        'tours' => 'Tours',
        'remote_sessions' => 'Remote support sessions',
        'attendances' => 'Attendances',
        'project_times' => 'Project times',
    ],

    'template' => [
        'example_required' => 'Example value (required)',
        'example_optional' => 'Example value (optional)',
        'download' => 'Download sample template',
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
        'periodLocked' => 'Period locked',
        'skipped' => 'Skipped',
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
            'xlsxUnreadable' => 'The Excel file is corrupted or not a valid XLSX format.',
            'xlsxEmpty' => 'The first worksheet of the Excel file contains no rows.',
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
            'project' => 'No project ":value" found — row moved to the assignment inbox.',
        ],
        'persist' => [
            'noBookingUser' => 'No bookable user found in the organisation.',
        ],
        // MVP-438: GoBD lock — no silent overwrite of reviewed periods.
        'periodLocked' => [
            'attendance' => 'Day :date is locked by day-close or month approval — row skipped.',
            'projectTime' => 'Period :date is already closed/exported — row skipped.',
        ],
        // MVP-438: iCal notice rows (deliberately conservative mapping).
        'ical' => [
            'allDay' => 'All-day event ":event" skipped (cannot count as attendance).',
            'noTime' => 'Event ":event" without a time skipped.',
            'category' => 'Event ":event" outside the category allowlist skipped.',
            'transparent' => 'Event ":event" marked free/out-of-office skipped.',
            'recurring' => 'Recurring event ":event": only the base instance was imported (series expansion comes later).',
            'unsupportedEntity' => 'iCal import is not supported for this import type.',
        ],
    ],
];
