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
        'stock_serial_none' => 'none (third-party part)',
        'stock_serial_hint' => 'Only parts from your own stock have one.',
        'serial_no_hint' => 'Free text; overwritten when a stock serial number is linked.',
        'empty' => 'No component recorded yet.',
        'saved' => 'Component recorded.',
        'replaced' => 'Component replaced — the old one stays in the history.',
        'removed' => 'Component removed.',
        'not_installed' => 'This component is no longer installed.',
        'foreign_organization' => 'Material usage and device belong to different organizations.',
        'usage_of_other_asset' => 'The material usage belongs to another device.',
        'already_adopted' => 'This material usage is already in the parts list.',
        'adopted' => 'Material added to the parts list.',
        'replace_hint' => '“:name” is removed and stays in the history with its removal date.',
        'label_hint' => 'For third-party parts without an article record.',
        'interval_hint' => 'Replacement interval in months — the due date follows from it.',
        'action' => [
            'add' => 'Add component',
            'replace' => 'Replace',
            'remove' => 'Remove',
            'adopt' => 'Adopt',
        ],
        'adopt' => ['heading' => 'Material from jobs', 'hint' => 'Material assigned to this device on a timesheet. “Adopt” adds it to the parts list with the job date as installation date.', 'column_date' => 'Job'],
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
            'stock_serial' => 'Stock serial number',
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
    'label' => [
        'print' => 'Label with QR',
    ],
    'nfc' => [
        'read' => 'Read NFC tag',
        'write' => 'Write NFC tag',
    ],
    // Zustand, Außerbetriebnahme, Eigentümerwechsel, Rücknahme (MVP-1574).
    'health' => [
        'label' => 'Condition',
        'hint' => 'Derived: critical with an active block or blocking defect, degraded with an open defect or overdue maintenance/inspection.',
    ],
    'decommission' => [
        'title' => 'Decommission asset',
        'action' => 'Decommission',
        'intro' => 'The asset is decommissioned for good. It can no longer be checked out, rented, reserved or scheduled afterwards; its record is kept.',
        'date' => 'Decommissioned on',
        'reason' => 'Reason',
        'since' => 'Decommissioned since :date',
        'by_disposal' => 'Disposed of through a disposal order.',
        'done' => 'Asset decommissioned.',
        'plans' => 'Active maintenance plans: :count — they will be paused.',
        'duties' => 'Active inspection duties: :count — they are suspended, open inspection dates are cancelled.',
        'error' => [
            'checked_out' => 'The asset is still checked out. Please take it back first.',
            'not_allowed' => 'The asset cannot be decommissioned from status “:status”.',
        ],
    ],
    // Wiederinbetriebnahme (MVP-1628)
    'recommission' => [
        'title' => 'Return asset to service',
        'action' => 'Return to service',
        'intro' => 'The asset becomes active again: it can be issued, lent, reserved and scheduled. Paused maintenance plans and dormant inspection duties stay off — resume them one by one.',
        'reason' => 'Reason for returning to service',
        'done' => 'Asset returned to service.',
        'error' => [
            'not_decommissioned' => 'Only a decommissioned asset can be returned to service.',
            'disposed' => 'The asset was disposed of through a disposal job and remains decommissioned.',
        ],
    ],
    'ownership' => [
        'title' => 'Change owner',
        'action' => 'Change owner',
        'current' => 'Currently: :owner',
        'owned_by' => 'Ownership',
        'customer_hint' => 'Only for ownership “Customer”; otherwise the customer link is removed.',
        'note' => 'Note on the change of owner',
        'note_hint' => 'Only stored when the customer changes; the change then appears in the asset dossier.',
        'done' => 'Change of owner recorded.',
        'error' => [
            'unchanged' => 'Ownership and customer are unchanged.',
            'forbidden' => 'A different customer is a change of owner; you lack the permission for that.',
        ],
    ],
    'return' => [
        'title' => 'Take asset back',
        'condition_in' => 'Condition on return',
        'condition_placeholder' => 'e.g. complete, housing scratched',
    ],
    'timeline' => [
        'ownership_changed' => 'Owner changed',
        'health_changed' => 'Condition changed',
        'blocked' => 'Blocked',
        'unblocked' => 'Block released',
    ],
    'block' => [
        'from_status' => 'From status “:status”.',
        'status_left' => 'Status changed.',
    ],
    'guard' => [
        'out_of_service' => ':asset is no longer in operation (:status) and cannot be used.',
    ],
    // Anschaffung an der Asset-Akte (MVP-1606).
    'acquisition' => [
        'title' => 'Acquisition',
        'supplier' => 'Acquired from',
        'date' => 'Acquisition date',
        'cost' => 'Acquisition cost',
    ],
];
