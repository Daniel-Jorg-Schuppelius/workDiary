<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reporting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'target' => [
        'nav' => 'Targets',
        'title' => 'Targets & benchmarks',
        'subtitle' => 'Store target values per metric – reports show target vs. actual and the deviation.',
        'create' => 'Add target',
        'edit' => 'Edit target',
        'empty' => 'No targets defined yet.',
        'metric_label' => 'Metric',
        'scope_label' => 'Scope',
        'scope_ref' => 'Scope object',
        'scope_ref_hint' => 'Only select for customer/project/employee.',
        'value_label' => 'Target value',
        'period_label' => 'Reference period',
        'valid_from' => 'Valid from',
        'valid_until' => 'Valid until',
        'note_label' => 'Note',
        'created' => 'Target was created.',
        'updated' => 'Target was updated.',
        'deleted' => 'Target was deleted.',
        'delete_confirm' => 'Really delete this target?',
        'none' => '–',
        'soll' => 'Target',
        'ist' => 'Actual',
        'deviation' => 'Deviation',
        'met' => 'met',
        'missed' => 'missed',
        'no_target' => 'No target',
        'metric' => [
            'contributionMargin' => 'Contribution margin (%)',
            'billableRate' => 'Billable rate (%)',
            'reworkShare' => 'Rework share (%)',
            'slaComplianceRate' => 'SLA compliance rate (%)',
            'utilization' => 'Utilization (%)',
        ],
        'scope' => [
            'org' => 'Organization (global)',
            'customer' => 'Customer',
            'project' => 'Project',
            'user' => 'Employee',
        ],
        'period' => [
            'month' => 'Month',
            'quarter' => 'Quarter',
            'year' => 'Year',
        ],
    ],

    'cohort' => [
        'nav' => 'Cohort comparison',
        'title' => 'Cohort comparison (before/after training)',
        'subtitle' => 'Compares a metric per employee for the period before and after acquiring a training.',
        'qualification' => 'Training / qualification',
        'metric' => [
            'billableRate' => 'Billable rate (%)',
            'reworkShare' => 'Rework share (%)',
        ],
        'metric_label' => 'Metric',
        'window' => 'Comparison window (days)',
        'choose' => 'Please select a training.',
        'member' => 'Employee',
        'acquired_on' => 'Acquired on',
        'before' => 'Before',
        'after' => 'After',
        'delta' => 'Δ',
        'improved' => 'Improved',
        'no_date' => 'no acquisition date',
        'no_date_hint' => 'Without a recorded acquisition date (qualification "valid from") no before/after split can be formed.',
        'no_data_window' => 'Not enough time entries in one of the windows.',
        'aggregate' => 'Cohort total (mean)',
        'members_with_date' => 'with acquisition date',
        'members_without_date' => 'without acquisition date',
        'improved_count' => 'improved',
        'data_note' => 'Acquisition date source: the qualification assignment "valid from". Metrics are derived from the same time-entry fields (billable/non-billable) as the economics view.',
    ],
];
