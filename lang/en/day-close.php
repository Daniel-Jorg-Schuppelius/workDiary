<?php
/*
 * Created on   : Fri Jun 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : day-close.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Daily close (MVP-015, docs/tagesabschluss.md) — page texts, validator
 * messages (§4), flash and error texts. Kept in parity across
 * de/en/fr/it/es; enum labels live in enums.php
 * (dayClosure.status / dayCorrection.status).
 */

return [
    'title' => 'Daily close',
    'title_day' => 'Daily close :day',

    'subtitle' => [
        'own' => 'Review the day, fill gaps and close it as fully recorded.',
        'other' => 'Daily close of :name.',
    ],

    'section' => [
        'attendance' => 'Attendance',
        'breaks' => 'Breaks',
        'entries' => 'Order and project times',
        'issues' => 'Gaps & warnings',
        'balance' => 'Balance',
        'corrections' => 'Correction requests',
    ],

    'field' => [
        'date' => 'Date',
        'recorded_break' => 'Recorded break',
        'required_break' => 'Required break',
        'target' => 'Target hours',
        'gross' => 'Attendance (gross)',
        'break' => 'Break',
        'net' => 'Net work',
        'booked' => 'Booked',
        'diff' => 'Difference',
        'day_balance' => 'Day balance',
        'month_balance' => 'Balance current month',
        'duration' => 'Duration',
        'project' => 'Order / project',
        'activity' => 'Activity',
        'comment' => 'Comment',
        'billable' => 'Billable',
        'reason' => 'Reason',
        'reason_placeholder' => 'Reason (at least :min characters)',
        'decision' => 'Decision',
    ],

    'action' => [
        'prev_day' => 'Previous day',
        'next_day' => 'Next day',
        'today' => 'Today',
        'pick_date' => 'Pick a date',
        'show_day' => 'Show day',
        'clock_in' => 'Clock in now',
        'clock_out' => 'Clock out now',
        'book_time' => 'Book time',
        'save' => 'Save',
        'close_day' => 'Close day',
        'request_correction' => 'Request correction',
        'reopen' => 'Reopen day',
        'approve' => 'Approve',
        'reject' => 'Reject',
        'cancel' => 'Cancel',
    ],

    'status' => [
        'attendance_open' => 'open',
        'comment_missing' => 'missing',
        'billable' => 'billable',
    ],

    'hint' => [
        'no_attendance' => 'No clock events on this day.',
        'attendance_correction_only' => 'Clock events can only be changed via a correction request.',
        'attendance_locked' => 'Attendance clock events are locked after a correction approval — only bookings can be changed until the day is closed again.',
        'no_entries' => 'No bookings on this day yet.',
        'break_recorded' => 'Break: :min min',
        'no_issues' => 'No findings — the day is recorded consistently.',
        'month_locked' => 'This day belongs to an approved month and is locked — closing and correction requests go through the monthly approval.',
        'correction_intro' => 'Describe what should be corrected on this day.',
        'reopen_intro' => 'The day is reopened without a correction request — the reason is stored in the audit log.',
    ],

    // The 7 consistency checks from §4 — key = check code
    // (DayClosureValidator), dots in the code map to nesting.
    'check' => [
        'attendance' => [
            'missing_close' => 'The time clock is still open — please clock out.',
        ],
        'time' => [
            'unallocated_minutes' => ':minutes minutes of attendance are not yet allocated to a booking.',
            'gap_in_attendance' => 'Attendance gap of :minutes minutes without a break marker.',
        ],
        'break' => [
            'required' => 'Required break not met: :taken of :required minutes recorded.',
        ],
        'balance' => [
            'threshold' => 'Day balance of :hours hours exceeds ±2 h.',
        ],
        'entry' => [
            'missing_comment' => ':count billable booking(s) without a comment.',
        ],
        'worktime' => [
            'overrun' => 'Net working time above 10 hours (:minutes minutes, ArbZG).',
        ],
        'unknown' => 'Unknown check: :code',
    ],

    'flash' => [
        'saved' => 'Day :day was saved.',
        'closed' => 'Day :day was closed.',
        'correction_requested' => 'Correction for :day was requested.',
        'correction_approved' => 'Correction for :day was approved.',
        'correction_rejected' => 'Correction for :day was rejected.',
        'reopened' => 'Day :day was reopened.',
    ],

    'errors' => [
        'future_day' => 'A future day cannot be closed.',
        'blocking_warnings' => 'The day has blocking warnings and cannot be closed.',
        'illegal_day_status' => 'Action not allowed: day status is :status.',
        'illegal_request_status' => 'Action not allowed: request status is :status.',
        'reason_too_short' => 'A reason of at least :n characters is required.',
        'month_locked' => 'The month is already approved or locked — please reopen the monthly approval first.',
        'owner_missing' => 'Daily close without a valid owner.',
        'closure_missing' => 'Correction request without an associated daily close.',
        // Tooltip reasons for the disabled close button (§2.6).
        'close_blocked' => [
            'future' => 'A future day cannot be closed.',
            'month_locked' => 'The month is already approved or locked.',
            'blocking' => 'Blocking warnings (⛔) must be resolved first.',
            'not_open' => 'The day is not open.',
        ],
    ],

    'modal' => [
        'correction_title' => 'Request correction',
        'reopen_title' => 'Reopen day',
    ],
];
