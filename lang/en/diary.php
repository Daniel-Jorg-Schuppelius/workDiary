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
];
