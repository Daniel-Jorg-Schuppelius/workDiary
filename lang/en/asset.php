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
    // Anlagen-Stückliste (Feature 118, MVP-607).
    'components' => [
        'title' => 'Bill of components',
        'empty' => 'No component recorded yet.',
        'saved' => 'Component recorded.',
        'replaced' => 'Component replaced — the old one stays in the history.',
        'removed' => 'Component removed.',
        'not_installed' => 'This component is no longer installed.',
        'foreign_organization' => 'Material usage and device belong to different organizations.',
        'replace_hint' => '“:name” is removed and stays in the history with its removal date.',
        'label_hint' => 'For third-party parts without an article record.',
        'interval_hint' => 'Replacement interval in months — the due date follows from it.',
        'action' => [
            'add' => 'Add component',
            'replace' => 'Replace',
            'remove' => 'Remove',
        ],
        'due' => ['heading' => 'Wear parts due', 'hint' => 'A suggestion for the next job — the technician decides what is actually replaced.'],
        'history' => ['heading' => 'History (removed and replaced parts)'],
        'column' => [
            'name' => 'Component',
            'article' => 'Article',
            'label' => 'Label (free text)',
            'position' => 'Position',
            'quantity' => 'Quantity',
            'unit' => 'Unit',
            'serial_no' => 'Serial number',
            'installed_on' => 'Installed on',
            'removed_on' => 'Removed on',
            'due_on' => 'Replacement due',
            'interval' => 'Interval (months)',
            'status' => 'Status',
            'note' => 'Note',
        ],
        'status' => [
            'installed' => 'Installed',
            'removed' => 'Removed',
            'replaced' => 'Replaced',
        ],
    ],
];
