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
        'schedule_type' => env('TIMESHEET_DEFAULT_SCHEDULE_TYPE', 'flextime'),
        'weekly_minutes' => (int) env('TIMESHEET_DEFAULT_WEEKLY_MINUTES', 2400),
        'daily_target_minutes' => (int) env('TIMESHEET_DEFAULT_DAILY_MINUTES', 480),
        // ISO-Wochentage (1 = Montag); als Liste in der ENV, z. B. "1,2,3,4,5".
        'working_days' => array_values(array_filter(array_map(
            static fn (string $day): int => (int) trim($day),
            explode(',', (string) env('TIMESHEET_DEFAULT_WORKING_DAYS', '1,2,3,4,5')),
        ), static fn (int $day): bool => $day >= 1 && $day <= 7)),
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

    // How long a user may correct their OWN TimeEntry after the entry date.
    // Admins always bypass this window (see TimeEntryPolicy). Additional hard
    // locks (timesheet signed/locked, entry exported) cannot be overridden by
    // the window — they require admin intervention.
    'edit_window' => [
        'days' => (int) env('TIMESHEET_EDIT_WINDOW_DAYS', 7),
    ],

    'pdf' => [
        'disk' => env('TIMESHEET_PDF_DISK', 'local'),
    ],

    // Which TimeEntry rows are considered "arbeitszeitwirksam" for the
    // Gleitzeit / WorkBalance reports. Defaults match a typical contract:
    //   - "work" and "travel" minutes count toward Ist-Arbeitszeit
    //   - "break" and "absence" activity types are NEVER counted (pauses
    //     are reported separately; absences are time off, not Ist).
    'flex' => [
        'count_kinds' => ['work', 'travel'],
        'exclude_activity_types' => ['break', 'absence'],
    ],

    // Statutory break rules (German ArbZG §4 by default):
    //   > 6 h work → at least 30 min break
    //   > 9 h work → at least 45 min break
    // When `auto_apply` is true, Attendance::saving() fills the gap into
    // `break_minutes_auto` whenever the recorded breaks fall short.
    'breaks' => [
        'rules' => [
            ['after_minutes' => 360, 'required_minutes' => 30],
            ['after_minutes' => 540, 'required_minutes' => 45],
        ],
        'auto_apply' => (bool) env('TIMESHEET_BREAK_AUTO_APPLY', true),
    ],

    'travel' => [        // Default reimbursement rate per kilometer in EUR per vehicle type.
        // Overridable per-organization later via DB settings.
        'rates' => [
            'private' => (float) env('TRAVEL_RATE_PRIVATE_KM', 0.30),
            'company' => (float) env('TRAVEL_RATE_COMPANY_KM', 0.00),
            'public_transport' => (float) env('TRAVEL_RATE_PUBLIC_KM', 0.00),
            'bicycle' => (float) env('TRAVEL_RATE_BICYCLE_KM', 0.05),
            'foot' => 0.0,
            'other' => (float) env('TRAVEL_RATE_OTHER_KM', 0.00),
        ],
        // Whether to automatically create a paired TimeEntry (kind=travel) when
        // started_at/ended_at are provided on a TravelLog.
        'auto_create_time_entry' => (bool) env('TRAVEL_AUTO_TIME_ENTRY', true),
    ],

    'providers' => [
        'lexoffice' => [
            'api_key' => env('LEXOFFICE_API_KEY', ''),
            'base_url' => env('LEXOFFICE_BASE_URL', 'https://api.lexoffice.io/v1'),
        ],
    ],
];
