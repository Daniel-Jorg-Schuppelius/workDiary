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
];
