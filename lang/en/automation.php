<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : automation.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'trigger' => [
        'expense_submitted' => 'Expense report submitted',
        'open_issue_created' => 'Open issue created',
        'procedure_deviation_recorded' => 'Procedure deviation recorded',
        'incoming_invoice_received' => 'Incoming invoice received',
    ],
    'action' => [
        'expense_approve' => 'Approve expenses',
        'diary_follow_up' => 'Create follow-up order',
        'incoming_invoice_discard' => 'Discard incoming invoice',
        'incoming_invoice_reject' => 'File incoming invoice as “not an invoice”',
        'incoming_invoice_assign' => 'Assign incoming invoice',
    ],
    'form' => [
        'title' => 'New automation rule',
        'trigger' => 'Trigger',
        'action' => 'Action',
        'action_hint' => 'The action must match the trigger.',
        'conditions' => 'Conditions (JSON)',
        'conditions_hint' => 'An empty condition {"all":[]} always applies. Examples:',
    ],
    'error' => [
        'invalid_json' => 'The conditions are not a valid JSON object.',
        'action_trigger_mismatch' => 'This action does not match the selected trigger.',
    ],
];
