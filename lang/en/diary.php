<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : diary.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'priority' => [
        'low' => 'Low',
        'normal' => 'Normal',
        'high' => 'High',
        'urgent' => 'Urgent',
    ],
    'location_mode' => [
        'onsite' => 'On site',
        'remote' => 'Remote',
        'hybrid' => 'Hybrid',
    ],
    'mode' => [
        'fixed' => 'Scheduled',
        'deadline' => 'Deadline',
        'window' => 'Window',
        'recurring' => 'Recurring',
        'backlog' => 'Backlog',
    ],
    'status' => [
        'Planned' => 'Planned',
        'Accepted' => 'Accepted',
        'InProgress' => 'In progress',
        'WaitingCustomer' => 'Waiting for response',
        'WaitingMaterial' => 'Waiting for material',
        'Completed' => 'Completed',
        'AcceptedFinal' => 'Signed off',
        'Invoiced' => 'Invoiced',
        'Cancelled' => 'Cancelled',
        'InReview' => 'In review',
        'ReviewQueried' => 'Query',
        'ReviewApproved' => 'Approved',
    ],
    'planned_duration' => [
        'label' => 'Planned duration (HH:MM)',
        'hint' => 'Empty: length of the time slot or duration of the appointment. Counts as the plan in Plan/actual, Order-type analysis and Staff capacity.',
        'format' => 'Please enter the planned duration as hours:minutes, e.g. 1:30.',
        'range' => 'The planned duration must be between 0:01 and 168:00.',
    ],
    // Kennzahl Plan/Ist am Auftrag (MVP-1552).
    'plan_actual' => [
        'label' => 'Plan/actual',
        'no_plan' => 'no plan',
        'own_only' => 'own times only',
    ],
    // Auftrags-Lebenszyklus (MVP-1596).
    'lifecycle' => [
        'action' => [
            'reject' => 'Return acceptance',
            'revert_invoiced' => 'Revert invoicing',
            'submit_review' => 'Submit for review',
            'withdraw_review' => 'Withdraw submission',
            'approve_review' => 'Approve',
            'query_review' => 'Raise a query',
            'resubmit_review' => 'Resubmit',
            'approve_and_complete' => 'Approve and complete',
        ],
        'reject' => [
            'title' => 'Return acceptance',
            'legend' => 'Return',
            'hint' => 'The order goes back to "Planned" and can be accepted by someone else. The reason is kept in the history.',
        ],
        'handover' => [
            'legend' => 'Acceptance',
            'accepted_by' => 'Accepted by (name at the customer)',
            'hint' => 'The protocol carries no customer signature. Please name the person who accepted the work at the customer.',
        ],
        'accepted_final_by' => 'Accepted by :name on :date',
        'invoiced_with' => 'Invoiced with :reference',
        'invoiced' => [
            'transfer_reference' => 'Transfer no. :id',
        ],
        'cancel' => [
            'run_abort_reason' => 'Order cancelled: :reason',
        ],
        'error' => [
            'stopwatch_running' => 'The order can only be completed once no stopwatch is running. Still active: :names.',
        ],
    ],
    // Periodenaufträge mit interner Prüfung (Feature 198), Folgeperiode und Demo.
    'period' => [
        'label' => 'Period',
        'badge' => 'Period: :period',
        'placeholder' => '2026, 2026-H1, 2026-Q1, 2026-03',
        'hint' => 'Year (2026), half-year (2026-H1), quarter (2026-Q1) or month (2026-03).',
        'invalid' => 'Please enter the period as a year (2026), half-year (2026-H1), quarter (2026-Q1) or month (2026-03).',
        'kind_mismatch' => 'This entry type requires a period of the following kind: :kinds.',
    ],
    'product_group' => [
        'label' => 'Product group',
        'invalid' => 'This product group does not exist in your organisation.',
    ],
    'review' => [
        'required_badge' => 'Review required',
        'reviewer_named' => 'Reviewed by :name',
        'submitted_by' => 'Submitted by :name on :date',
        'reviewer_label' => 'Reviewer',
        'reviewer_any' => 'Anyone with the review right',
        'reviewer_hint' => 'Without a selection, anyone with the “Review orders” right reviews – never the person who submits.',
        'waive_action' => 'Waive review',
        'require_action' => 'Require review',
        'waive_hint' => 'Without a review the order is completed directly. The change is recorded with your reason in the history and the audit log.',
        'require_hint' => 'With a review requirement the order is submitted for review before completion and approved by a second person.',
        'approve_complete_hint' => 'Here the approval is also the completion: you approve and complete the order in one step.',
        'query' => [
            'title' => 'Query on the review',
            'hint' => 'The order goes back to the person working on it with your question and is then resubmitted. The question is recorded in the history.',
            'legend' => 'Query',
            'question' => 'Your question',
        ],
        'error' => [
            'own_submission' => 'Whoever submitted an order for review cannot approve it or raise a query themselves.',
            'requirement_locked' => 'The review requirement can no longer be changed in status “:status”.',
            'question_required' => 'A query needs a text.',
            'not_in_review' => 'The order is no longer awaiting review.',
        ],
        'flash' => [
            'waived' => 'Review requirement waived for this order.',
            'required' => 'Review requirement switched on for this order.',
        ],
        'inbox_reference' => 'Submitted by :name on :date',
        'inbox_type' => 'Order review',
        'queue' => [
            'title' => 'For review',
            'subtitle' => 'Orders you may review – by due date, then by submission date. Your own submissions do not appear here.',
            'empty_title' => 'Nothing to review',
            'empty_message' => 'No order is currently awaiting your review.',
            'column' => [
                'order' => 'Order',
                'submitted' => 'Submitted',
                'actions' => 'Actions',
            ],
        ],
        'widget' => [
            'title' => 'For review',
            'description' => 'Orders awaiting your internal review – by due date, then by submission date.',
            'open_queue' => 'All →',
        ],
    ],
    'follow_up' => [
        'action' => 'Create next period',
        'title' => 'Create next period :period',
        'submit' => 'Create',
        'copied' => 'Customer, entry type, product group, responsibility, review requirement and reviewer are copied, and the procedures start as new runs. Open notes appear in the new order.',
        'not_copied' => 'Content and notes, times and the result are not copied.',
        'exists_hint' => 'This order already exists for :period:',
        'content' => 'Next period :period of order #:id.',
        'error' => [
            'no_series' => 'A next period is only available for orders with a period, customer and entry type.',
            'exists' => 'The order already exists for this period (#:id).',
            'selection' => 'Please choose an entry type and a period.',
        ],
        'flash' => [
            'created' => 'Order for :period created.',
            'bulk' => ':created orders for :period created, :skipped skipped.',
        ],
        'bulk' => [
            'title' => 'Next period in bulk',
            'subtitle' => 'Create the order of the next period for all orders of an entry type and period – preview first, then create.',
            'choose_title' => 'Choose entry type and period',
            'choose_message' => 'The preview shows which orders will be created and which already exist.',
            'none_title' => 'No orders',
            'none_message' => 'There are no orders of this entry type in :period.',
            'summary' => ':count orders in :from, :creatable of them new for :to.',
            'limit' => 'At most :limit orders are processed at once.',
            'submit' => 'Create :count orders',
            'column' => [
                'source' => 'Order',
                'target' => 'New period',
                'result' => 'Result',
            ],
            'result' => [
                'create' => 'will be created',
                'exists' => 'already exists',
                'no_customer' => 'no customer',
                'duplicate_source' => 'duplicate in preview',
            ],
        ],
    ],
    'demo' => [
        'entry_type' => 'Annual order (demo)',
        'title' => 'Annual order :year',
        'content' => 'Annual order for demonstration: collect documents, process them, submit for review.',
        'approval_note' => 'Reviewed, no remarks.',
        'summary' => 'Documents complete, result submitted.',
        'carry_forward' => 'Next year, request the receipts by the end of February.',
    ],
    'entry_type' => [
        'legend' => 'Period and review',
        'description' => 'Period orders (year, half-year, quarter, month) and internal review before completion.',
        'requires_period' => 'Period required when creating',
        'requires_period_hint' => 'Without a selection the period is optional. With several kinds, any of them fits.',
        'requires_review' => 'Review required before completion (four-eyes principle)',
    ],
];
