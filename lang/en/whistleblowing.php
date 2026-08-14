<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : whistleblowing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */
/*
 * Strings for the whistleblowing module (categories etc.).
 */

return [
    'category' => [
        'corruption' => 'Corruption and bribery',
        'fraud' => 'Fraud, embezzlement and theft',
        'money_laundering' => 'Money laundering and terrorist financing',
        'procurement' => 'Procurement and competition violations',
        'data_protection' => 'Data protection and information security',
        'product_safety' => 'Product safety and consumer protection',
        'environment' => 'Environmental and occupational safety violations',
        'discrimination' => 'Discrimination, harassment and abuse of power',
        'policy_violation' => 'Violation of internal policies',
        'other' => 'Other potential legal violation',
    ],
    'status' => [
        'submitted' => 'Submitted',
        'acknowledged' => 'Acknowledged',
        'triage' => 'Triage',
        'investigating' => 'Investigating',
        'waiting_reporter' => 'Awaiting reporter',
        'referred' => 'Referred',
        'closed_substantiated' => 'Closed – substantiated',
        'closed_unsubstantiated' => 'Closed – unsubstantiated',
        'closed_out_of_scope' => 'Closed – out of scope',
        'closed_duplicate' => 'Closed – duplicate',
        'retention_review' => 'Retention review',
        'legal_hold' => 'Legal hold',
        'deleted' => 'Deleted',
    ],
    'reporter_status' => [
        'received' => 'Received and under review',
        'in_progress' => 'In progress',
        'awaiting_you' => 'Awaiting your response',
        'closed' => 'Closed',
    ],
    'priority' => [
        'normal' => 'Normal',
        'high' => 'High',
        'critical' => 'Critical',
    ],
    'role' => [
        'owner' => 'Owner',
        'processor' => 'Processor',
        'reviewer' => 'Reviewer',
    ],
];
