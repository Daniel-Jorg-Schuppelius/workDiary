<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'lifecycle' => [
        'in_operation' => 'In operation',
        'retired' => 'Replaced',
        'decommissioned' => 'Decommissioned',
    ],
    'dossier' => [
        'title' => 'Object file',
        'back' => 'Back to asset',
        'generated_at' => 'Generated on',
        'lifecycle' => 'Lifecycle',
        'master_data' => 'Master data',
        'health' => 'Condition',
        'commissioned' => 'Commissioned',
        'decommissioned' => 'Decommissioned',
        'warranty' => 'Warranty until',
        'warranty_expired' => 'expired',
        'in_service_days' => 'In service (days)',
        'room_requirements' => 'Room-specific requirements',
        'maintenance' => 'Maintenance',
        'next_due' => 'Next due',
        'last_run' => 'Last performed',
        'due' => 'Due',
        'scheduled' => 'Scheduled',
        'assignments' => 'Check-outs / returns',
        'checked_out' => 'Checked out',
        'assignee' => 'Assignee',
        'returned' => 'Returned',
        'open' => 'Open',
        'defects' => 'Defects / blocks',
        'blocks' => 'Blocks',
        'orders' => 'Orders',
        'timeline' => 'Lifecycle history',
        'event' => [
            'asset.audit' => 'Asset event',
            'order.linked' => 'Order linked',
            'protocol.linked' => 'Protocol linked',
            'material.linked' => 'Material usage linked',
            'attachment.linked' => 'Attachment added',
            'assignment.checkedOut' => 'Checked out',
            'assignment.returned' => 'Returned',
            'defect.reported' => 'Defect reported',
            'defect.resolved' => 'Defect resolved',
            'maintenance.completed' => 'Maintenance performed',
            'unknown' => 'Event',
        ],
    ],
];
