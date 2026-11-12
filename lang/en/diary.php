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
];
