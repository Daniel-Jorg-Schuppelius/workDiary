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
];
