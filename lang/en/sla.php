<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sla.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'remaining' => ':min min left',
    'overdue_by' => ':min min overdue',
    'diary_panel_heading' => 'Linked service tickets',

    'report' => [
        'title' => 'SLA report',
        'nav' => 'SLA',
        'subtitle' => 'SLA violations, compliance rate and causes in the period.',
        'total_tickets' => 'Tickets with SLA',
        'violations' => 'Violations',
        'met' => 'Met',
        'compliance_rate' => 'Compliance rate',
        'by_kind' => 'By type',
        'by_priority' => 'By priority',
        'by_customer' => 'By customer',
        'by_cause' => 'By cause',
        'kind' => 'Type',
        'cause' => 'Cause',
        'no_causes' => 'No causes recorded.',
        'no_violations' => 'No violations in the period.',
        'violation_list' => 'Violation list',
        'target' => 'Target',
        'breached_at' => 'Detected',
        'overdue' => 'Overdue (min)',
        'status' => 'Status',
        'acknowledged_badge' => 'Acknowledged',
        'open_badge' => 'Open',
        'acknowledge_btn' => 'Acknowledge',
        'acknowledged' => 'Violation acknowledged.',
        'no_customer' => 'No customer',
        'cause_unspecified' => 'Unspecified',
        'section' => 'Section',
        'metric' => 'Metric',
        'value' => 'Value',
        'overview' => 'Overview',
        'quotas_heading' => 'Included-time quotas',
        'no_quotas' => 'No quotas configured.',
        'quota_usage' => ':consumed / :included h (:period)',
        'quota_over' => ':min min over quota',
    ],
];
