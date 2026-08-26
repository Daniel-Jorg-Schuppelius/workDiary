<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'report' => [
        'title' => 'Working-time compliance',
        'nav' => 'Working-time compliance',
        'subtitle' => 'Violations of the Working Hours Act based on the actually recorded working time.',
        'empty' => 'No violations in the period.',
        'thresholds_note' => 'Thresholds (ArbZG): max. :daily net/day · min. :rest rest period · max. avg :weekly/week · mandatory breaks 30 min over 6 h, 45 min over 9 h.',
        'corrected' => 'corrected',
        'corrected_hint' => 'An approved time correction exists for this day.',
        'drilldown' => 'Open day closure',
        'filter' => [
            'kind' => 'Violation type',
            'all' => 'All types',
            'category' => 'Area',
            'all_categories' => 'All areas',
        ],
        'kpi' => [
            'total' => 'Total violations',
            'employees' => 'Affected employees',
        ],
        'kind' => [
            'maxDailyHours' => 'Maximum daily hours',
            'restPeriod' => 'Rest period',
            'breakMissing' => 'Mandatory break',
            'maxWeeklyHours' => 'Maximum weekly hours',
            'frameTime' => 'Working time frame',
            'coreTime' => 'Core working hours',
            'entryBreakMissing' => 'Mandatory break (project time)',
            'missingCheckout' => 'Missing check-out',
            'freeDayStamp' => 'Stamp on a day off',
            'absenceStamp' => 'Stamp during absence',
            'attendanceFrameTime' => 'Working time frame (clockings)',
            'lateRecording' => 'Late recording (MiLoG)',
            'sixMonthAverage' => 'Six-month average (§ 3 ArbZG)',
            'nightWork' => 'Night work above 8 h (§ 6 ArbZG)',
            'substituteRestDay' => 'Missing substitute rest day (§ 11 ArbZG)',
            'freeSundays' => 'Too few free Sundays (§ 11 ArbZG)',
            // Feature 144 (MVP-719): Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV).
            'dailyDriving' => 'Daily driving time (Art. 6 Reg. 561/2006)',
            'weeklyDriving' => 'Weekly driving time (Art. 6 Reg. 561/2006)',
            'fortnightDriving' => 'Fortnightly driving time (Art. 6 Reg. 561/2006)',
            'drivingBreakMissing' => 'Driving break missing (Art. 7 Reg. 561/2006)',
            'dailyRest' => 'Daily rest period (Art. 8 Reg. 561/2006)',
            'weeklyRest' => 'Weekly rest period (Art. 8 Reg. 561/2006)',
        ],
        'unit' => [
            'days' => '{1} :count day|[2,*] :count days',
        ],
        'severity' => [
            'error' => 'Violation',
            'warning' => 'Notice',
        ],
        'col' => [
            'date' => 'Date',
            'kind' => 'Type',
            'value' => 'Value',
            'threshold' => 'Threshold',
            'severity' => 'Severity',
        ],
        'csv' => [
            'employee' => 'Employee',
            'date' => 'Date',
            'kind' => 'Type',
            'severity' => 'Severity',
            'value' => 'Value',
            'threshold' => 'Threshold',
            'corrected' => 'Corrected',
            'yes' => 'yes',
        ],
    ],
    'history' => [
        'title' => 'Compliance violations',
        'nav' => 'Violation history',
        'subtitle' => 'Persisted ArbZG violations with processing status and acknowledgement.',
        'to_report' => 'Detail report',
        'to_dashboard' => 'Dashboard',
        'filter' => [
            'status' => 'Status',
            'all' => 'All statuses',
            'category' => 'Category',
        ],
        'col' => [
            'employee' => 'Employee',
            'status' => 'Status',
        ],
        'empty' => 'No persisted violations.',
        'note_placeholder' => 'Reason (required for “accepted”)',
        'btn' => [
            'acknowledge' => 'Acknowledge',
            'accept' => 'Accept',
            'correction' => 'Correction request',
        ],
        'category' => [
            'arbzg' => 'ArbZG',
            'plausibility' => 'Unresolved cases',
            'drivingTime' => 'Driving times',
        ],
        'acknowledged' => 'Violation updated.',
        'error' => [
            'invalid_status' => 'Invalid target status.',
            'not_acknowledgeable' => 'This violation can no longer be acknowledged.',
            'note_required' => 'A reason is required for “accepted”.',
        ],
    ],
    'milog' => [
        'button' => 'MiLoG evidence (customs)',
        'csv' => [
            'employee' => 'Employee',
            'personnel_number' => 'Personnel number',
            'date' => 'Date',
            'start' => 'Start',
            'end' => 'End',
            'breaks' => 'Breaks (min)',
            'duration' => 'Duration',
        ],
    ],
    'driving' => [
        'button' => 'Driving time evidence',
        'title' => 'Driving and rest time evidence',
        'thresholds_note' => 'Driving/rest times (Reg. (EC) 561/2006 / FPersV): max. 9 h driving/day (10 h twice a week) · 56 h/week · 90 h/fortnight · 45 min break after 4.5 h (splittable 15 + 30) · rest 11 h/day (max. 3× per week 9 h) · 45 h/week (24 h with compensation).',
        'disclaimer' => 'Data basis are the recorded trips (logbook) with flagged vehicles; tachograph/DTCO data is not read. No legal advice.',
        'csv' => [
            'driver' => 'Driver',
            'personnel_number' => 'Personnel number',
            'date' => 'Date',
            'vehicles' => 'Vehicles',
            'start' => 'First departure',
            'end' => 'Last arrival',
            'driving' => 'Driving time',
            'longest_stint' => 'Longest driving stint without break',
            'breaks' => 'Breaks (min)',
            'rest_before' => 'Rest before',
            'findings' => 'Findings',
        ],
        'badge' => [
            'label' => 'Driving time',
            'remaining' => ':remaining left',
            'until_break' => 'Break in :until',
            'break_due' => 'Break due',
            'exhausted' => 'Daily driving time exhausted',
            'title' => 'Remaining daily driving time :daily (limit :limit) · next break in :until · weekly remaining :weekly · fortnight :fortnight',
        ],
    ],
];
