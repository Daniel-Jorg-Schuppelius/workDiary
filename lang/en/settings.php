<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'tabs' => [
        'pagination' => 'Lists',
        'invoicing' => 'Invoicing',
        'uploads' => 'File uploads',
        'validation' => 'Input limits',
        'notifications' => 'Notifications',
        'ui' => 'Interface',
    ],
    'hint' => 'Leave empty to use the system default.',
    'pagination' => [
        'heading' => 'Page sizes',
        'description' => 'Number of items per page in list views.',
        'timesheets' => 'Timesheets',
        'duty_plans' => 'Duty plans',
        'customers' => 'Customers',
        'customer_search' => 'Customer search (type-ahead)',
        'customer_attachments' => 'Customer attachments',
        'organizations' => 'Organizations',
        'tours' => 'Tours',
        'vehicles' => 'Vehicles',
        'tags' => 'Tags',
        'archive' => 'Archive',
        'dashboard_recent' => 'Dashboard: recent items',
    ],
    'invoicing' => [
        'heading' => 'Invoicing defaults',
        'description' => 'Values pre-filled when creating a new invoice.',
        'default_tax_rate' => 'Default tax rate (%)',
        'default_currency' => 'Default currency (ISO-4217)',
        'time_unit' => 'Time unit for positions',
    ],
    'uploads' => [
        'heading' => 'Upload size limits (KB)',
        'description' => 'Maximum upload sizes, in kilobytes.',
        'csv_import_kb' => 'CSV import',
        'customer_attachment_kb' => 'Customer attachment',
    ],
    'validation' => [
        'heading' => 'Input limits',
        'description' => 'Character and range limits for form fields.',
        'attendance' => [
            'heading' => 'Attendance',
            'note_max' => 'Note, max characters',
            'device_max' => 'Device ID, max characters',
            'break_minutes_max' => 'Break, max minutes',
        ],
        'tag' => [
            'heading' => 'Tags',
            'name_max' => 'Tag name, max characters',
        ],
        'comment' => [
            'heading' => 'Comments',
            'body_max' => 'Comment body, max characters',
        ],
        'duty_plan' => [
            'heading' => 'Duty plans',
            'note_max' => 'Note, max characters',
        ],
    ],
    'notifications' => [
        'heading' => 'Push notifications',
        'description' => 'Push message behavior.',
        'push' => [
            'body_truncate' => 'Message preview, max characters',
        ],
    ],
    'ui' => [
        'heading' => 'Interface behavior',
        'description' => 'Visual and interactive UI behavior.',
        'calendar' => [
            'heading' => 'Calendar',
            'slot_minutes' => 'Slot length in minutes',
        ],
        'dashboard' => [
            'heading' => 'Dashboard',
            'recent_limit' => 'Number of recent items',
        ],
        'search' => [
            'heading' => 'Search',
            'results_limit' => 'Default result limit',
        ],
    ],
    'reset' => 'Reset to default',
    'placeholder_default' => 'Default :value',
];
