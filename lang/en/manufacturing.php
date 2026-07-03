<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Manufacturing',

    'capacity' => [
        'title' => 'Capacity',
        'subtitle' => 'Work centers and load (incl. setup time) for the selected period',
        'day' => 'Day',
        'period_note' => 'Utilisation across the header period :from – :to (capacity = daily capacity × days).',
        'add' => 'New work center',
        'empty' => 'No work centers yet.',
        'work_center' => 'Work center',
        'code' => 'Code',
        'capacity' => 'Capacity',
        'planned' => 'Planned',
        'free' => 'Free',
        'utilization' => 'Utilization',
        'setup' => 'Setup time',
        'assign' => 'Assign work center',
        'minutes' => 'Minutes',
        'flash' => [
            'created' => 'Work center created.',
            'assigned' => 'Work center assigned.',
            'assign_failed' => 'Assignment not possible.',
        ],
    ],

    'planning' => [
        'title' => 'Production planning',
        'subtitle' => 'Multi-level material requirements (MRP) and quality metrics',
        'explode' => 'Explode requirements',
        'requirements' => 'Material requirements',
        'no_bom' => 'No bill of materials.',
        'level' => 'Level',
        'source' => 'Source',
        'make' => 'Make',
        'buy' => 'Buy',
        'gross' => 'Gross',
        'net' => 'Net',
        'quality' => 'Quality metrics',
        'yield' => 'Yield',
        'scrap_rate' => 'Scrap rate',
        'rework_rate' => 'Rework rate',
        'spc' => 'SPC (measurement steps)',
        'measurement' => 'Measurement',
        'out_of_spec' => 'Out of tolerance',
    ],

    'procurement_mode' => [
        'in_house' => 'In-house',
        'purchase' => 'Purchase',
        'subcontract' => 'Subcontract',
    ],

    'quantity_kind' => [
        'fixed' => 'Fixed quantity',
        'per_unit' => 'Quantity per unit',
        'ratio' => 'Ratio (recipe)',
    ],
    'delivery_note' => [
        'title' => 'Delivery note',
        'date' => 'Delivery date',
        'order' => 'Order',
        'recipient' => 'Recipient',
        'warehouse' => 'Warehouse',
        'no_customer' => 'No customer set',
        'footer_note' => 'Hand-over record only — not an invoice. Please confirm receipt.',
        'col' => [
            'sku' => 'Item no.',
            'name' => 'Description',
            'qty' => 'Quantity',
            'unit' => 'Unit',
        ],
    ],
    'record' => [
        'title' => 'Production record',
        'generated_at' => 'Generated at',
        'reported_at' => 'Reported at',
        'reported_by' => 'Reported by',
        'no_reports' => 'No reports yet.',
        'procedure' => 'Procedure / work plan',
        'footer_note' => 'Production record based on the recorded partial reports — not an invoicing document.',
    ],
    'parameter_type' => [
        'number' => 'Number',
        'measure' => 'Measure (with unit)',
        'choice' => 'Choice',
        'text' => 'Text',
        'date' => 'Date',
        'bool' => 'Yes/No',
    ],
    'parameter' => [
        'error' => [
            'required' => 'Required parameter ":param" is missing.',
            'invalid' => 'Parameter ":param" has an invalid value.',
        ],
    ],

    'status' => [
        'draft' => 'Draft',
        'released' => 'Released',
        'in_progress' => 'In progress',
        'waiting' => 'Waiting',
        'blocked' => 'Blocked',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],

    'facturation_status' => [
        'pending' => 'Pending',
        'handed_over' => 'Handed over',
        'invoiced' => 'Invoiced',
        'failed' => 'Failed',
        'not_required' => 'Not required',
    ],

    'bom_override' => [
        'disable' => 'Disable',
        'override_qty' => 'Override quantity',
        'add' => 'Add',
    ],

    'substitute_status' => [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],

    'procurement_status' => [
        'open' => 'Open',
        'ordered' => 'Ordered',
        'closed' => 'Closed',
    ],

    'order' => [
        'title' => 'Manufacturing orders',
        'subtitle' => 'Plan, release and report manufacturing/assembly orders.',
        'empty' => 'No manufacturing orders yet.',
        'action' => [
            'create' => 'Create order',
            'release' => 'Release',
            'start' => 'Start',
            'reserve' => 'Reserve material',
            'report' => 'Report',
            'deliver' => 'Deliver',
            'push_lexoffice' => 'Send to Lexoffice',
            'subcontract' => 'Subcontract',
            'cancel' => 'Cancel',
            'consume' => 'Post consumption',
            'procedure_run' => 'Procedure run',
        ],
        'field' => [
            'target_qty' => 'Target quantity',
            'good' => 'Good quantity',
            'scrap' => 'Scrap',
            'rework' => 'Rework',
            'produced' => 'Produced',
            'quantity' => 'Quantity',
            'materials' => 'Materials',
            'reports' => 'Reports',
            'article' => 'Article',
            'deliveries' => 'Deliveries',
            'facturation_status' => 'Invoicing status',
            'consumed' => 'Consumed',
            'actual_cost' => 'Actual cost',
        ],
        'flash' => [
            'created' => 'Order created.',
            'released' => 'Order released.',
            'started' => 'Order started.',
            'reserved' => 'Material reserved.',
            'reported' => 'Report recorded.',
            'delivered' => 'Delivered.',
            'lexoffice_pushed' => 'Delivery note sent to Lexoffice.',
            'subcontracted' => 'Sent to supplier (order created).',
            'subcontract_failed' => 'Subcontracting not possible.',
            'cancelled' => 'Order cancelled.',
            'deliver_needs_variant_warehouse' => 'Delivery requires a variant and a warehouse.',
            'consumed' => 'Consumption posted.',
            'consume_not_allowed' => 'Consumption is only possible for released or running orders.',
        ],
    ],
];
