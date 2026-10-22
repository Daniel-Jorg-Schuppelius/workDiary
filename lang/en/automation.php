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
        'user_created' => 'Employee created',
        'asset_checked_out' => 'Equipment checked out',
        'expense_submitted' => 'Expense report submitted',
        'open_issue_created' => 'Open issue created',
        'procedure_deviation_recorded' => 'Procedure deviation recorded',
        'incoming_invoice_received' => 'Incoming invoice received',
    ],
    'action' => [
        'learning_enroll' => 'Enrol in course',
        'expense_approve' => 'Approve expenses',
        'diary_follow_up' => 'Create follow-up order',
        'incoming_invoice_discard' => 'Discard incoming invoice',
        'incoming_invoice_reject' => 'File incoming invoice as “not an invoice”',
        'incoming_invoice_assign' => 'Assign incoming invoice',
    ],
    'form' => [
        'target' => 'Target',
        'target_hint' => 'Only for actions with a target, such as the course of an enrolment.',
        'target_course' => 'Course',
        'title' => 'New automation rule',
        'trigger' => 'Trigger',
        'action' => 'Action',
        'action_hint' => 'The action must match the trigger.',
        'conditions' => 'Conditions (JSON)',
        'conditions_hint' => 'An empty condition {"all":[]} always applies. Examples:',
    ],
    'error' => [
        'target_required' => 'Please choose a target from the list.',
        'invalid_json' => 'The conditions are not a valid JSON object.',
        'action_trigger_mismatch' => 'This action does not match the selected trigger.',
    ],
];
