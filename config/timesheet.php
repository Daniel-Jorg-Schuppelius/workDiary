<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : timesheet.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [

    'defaults' => [
        'weekly_minutes' => (int) env('TIMESHEET_DEFAULT_WEEKLY_MINUTES', 2400),
        'daily_target_minutes' => (int) env('TIMESHEET_DEFAULT_DAILY_MINUTES', 480),
        'working_days' => [1, 2, 3, 4, 5],
        'core_start' => env('TIMESHEET_CORE_START', '09:00'),
        'core_end' => env('TIMESHEET_CORE_END', '15:00'),
        'frame_start' => env('TIMESHEET_FRAME_START', '06:00'),
        'frame_end' => env('TIMESHEET_FRAME_END', '20:00'),
        'break_after_minutes' => (int) env('TIMESHEET_BREAK_AFTER', 360),
        'break_minutes' => (int) env('TIMESHEET_BREAK_MIN', 30),
    ],

    'signature' => [
        'max_kb' => (int) env('TIMESHEET_SIGNATURE_MAX_KB', 1000),
        'magic_minutes' => (int) env('TIMESHEET_MAGIC_MINUTES', 1440),
    ],

    'pdf' => [
        'disk' => env('TIMESHEET_PDF_DISK', 'local'),
    ],

    'providers' => [
        'lexoffice' => [
            'api_key' => env('LEXOFFICE_API_KEY', ''),
            'base_url' => env('LEXOFFICE_BASE_URL', 'https://api.lexoffice.io/v1'),
        ],
    ],
];
