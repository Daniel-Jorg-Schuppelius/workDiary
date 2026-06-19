<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Inventory',

    'mode' => [
        'local' => 'Local (WorkDiary keeps stock)',
        'external' => 'External (warehouse system leads)',
        'read_only' => 'Read-only (externally led)',
    ],

    'state' => [
        'physical' => 'Physical',
        'reserved' => 'Reserved',
        'blocked' => 'Blocked',
        'quality' => 'Quality check',
        'damaged' => 'Damaged',
        'scrap' => 'Scrap',
    ],

    'ownership' => [
        'own' => 'Own stock',
        'customer' => 'Customer material',
        'consignment' => 'Consignment',
        'supplier' => 'Supplier material',
        'project' => 'Project-bound',
    ],

    'movement' => [
        'receipt' => 'Goods receipt',
        'issue' => 'Issue',
        'return' => 'Return',
        'transfer_out' => 'Transfer (out)',
        'transfer_in' => 'Transfer (in)',
        'reserve' => 'Reservation',
        'release_reservation' => 'Reservation released',
        'scrap' => 'Scrap',
        'correction' => 'Correction/stocktake difference',
        'finished_good_receipt' => 'Finished good receipt',
    ],

    'warehouses' => 'Warehouses',
    'stock' => 'Stock',
    'subtitle' => [
        'warehouses' => 'Manage the tenant’s warehouses.',
        'stock' => 'Availability and movements per warehouse.',
    ],
    'action' => [
        'create_warehouse' => 'Create warehouse',
        'edit_warehouse' => 'Edit warehouse',
        'book' => 'Post movement',
    ],
    'field' => [
        'code' => 'Code',
        'default' => 'Default',
        'available' => 'Available',
        'physical' => 'Physical',
        'reserved' => 'Reserved',
        'location_note' => 'Location note',
        'warehouse' => 'Warehouse',
        'variant' => 'Variant',
        'quantity' => 'Quantity',
        'movement' => 'Movement',
        'ownership' => 'Ownership',
        'allow_negative' => 'Allow negative stock',
    ],
    'empty' => [
        'warehouses' => 'No warehouses created yet.',
        'stock' => 'No movements in this warehouse.',
        'no_selection' => 'No warehouse selected.',
    ],
    'confirm' => [
        'delete_warehouse' => 'Really delete this warehouse? Only possible without movements.',
    ],
    'flash' => [
        'warehouse_created' => 'Warehouse created.',
        'warehouse_updated' => 'Warehouse updated.',
        'warehouse_deleted' => 'Warehouse deleted.',
        'warehouse_delete_blocked' => 'Warehouse cannot be deleted: movements exist.',
        'movement_posted' => 'Movement posted.',
    ],
    'reservation_status' => [
        'active' => 'Active',
        'fulfilled' => 'Fulfilled',
        'released' => 'Released',
        'cancelled' => 'Cancelled',
    ],
    'count_status' => [
        'counting' => 'Counting',
        'review' => 'Review',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ],
    'count_ui' => [
        'title' => 'Stocktake',
        'open' => 'Open stocktake',
        'save' => 'Save counts',
        'apply' => 'Post differences',
        'book' => 'Book',
        'counted' => 'Counted',
        'difference' => 'Difference',
        'counted_at' => 'Count time',
        'no_counts' => 'No stocktakes for this warehouse yet.',
        'no_selection' => 'No warehouse selected.',
        'opened' => 'Stocktake opened, book stock frozen.',
        'saved' => 'Counts saved.',
        'applied' => 'Differences posted as corrections.',
        'cycle' => 'Cycle (ABC)',
        'cycle_open' => 'Count cycle',
        'cycle_empty' => 'No due items in this class.',
    ],
    'overview' => [
        'avg' => 'Avg cost',
        'value' => 'Value',
        'priority' => 'Priority',
        'min_stock' => 'Minimum stock',
        'reorder_point' => 'Reorder point',
        'release' => 'Release',
        'set_levels' => 'Set levels',
        'reservations' => 'Reservations',
        'below_reorder' => 'Procurement needs',
        'shortfall' => 'Shortfall',
        'no_reservations' => 'No active reservations.',
        'reservation_released' => 'Reservation released.',
        'levels_saved' => 'Minimum/reorder levels saved.',
    ],

    'serial' => [
        'title' => 'Serial numbers',
        'subtitle' => 'Per-unit life cycle, proof of shipment and authenticity check.',
        'empty' => 'No serial numbers yet.',
        'blocked_default' => 'Blocked',
        'status' => [
            'created' => 'Created',
            'in_stock' => 'In stock',
            'reserved' => 'Reserved',
            'shipped' => 'Shipped',
            'returned' => 'Returned',
            'blocked' => 'Blocked',
            'scrapped' => 'Scrapped',
        ],
        'source' => [
            'manufactured' => 'In-house production',
            'purchased' => 'Purchased',
        ],
        'field' => [
            'serial_no' => 'Serial number',
            'status' => 'Status',
            'source' => 'Source',
            'article' => 'Article',
            'variant' => 'Variant',
            'warehouse' => 'Warehouse',
            'customer' => 'Customer',
            'order' => 'Manufacturing order',
            'delivery' => 'Delivery',
            'shipped_at' => 'Shipped at',
            'reason' => 'Reason',
        ],
        'action' => [
            'block' => 'Block',
            'unblock' => 'Unblock',
            'scrap' => 'Scrap',
            'verify' => 'Device passport',
            'search' => 'Search',
        ],
        'flash' => [
            'blocked' => 'Serial number blocked.',
            'unblocked' => 'Serial number unblocked.',
            'scrapped' => 'Serial number scrapped.',
        ],
        'verify' => [
            'title' => 'Device passport / authenticity check',
            'subtitle' => 'Enter a serial number to check its status and origin.',
            'placeholder' => 'Serial number …',
            'not_found' => 'No serial number found – authenticity not confirmed.',
            'found' => 'Serial number found.',
        ],
    ],

    'outbox' => [
        'status' => [
            'pending' => 'Pending',
            'processing' => 'Delivering',
            'confirmed' => 'Confirmed',
            'failed' => 'Failed',
            'compensation_required' => 'Compensation required',
        ],
    ],

    'valuation' => [
        'method' => [
            'moving_average' => 'Moving average',
            'fifo' => 'FIFO',
            'fefo' => 'FEFO (first expired)',
        ],
    ],

    'scan' => [
        'action' => [
            'receipt' => 'Goods receipt',
            'issue' => 'Issue',
            'transfer' => 'Transfer',
        ],
        'title' => 'Scan',
        'subtitle' => 'Scan a code and book',
        'code' => 'Code',
        'qty' => 'Quantity',
        'book' => 'Book',
        'action_label' => 'Action',
        'target' => 'Target warehouse',
        'invalid' => 'Invalid input.',
        'booked' => 'Booking recorded.',
    ],

    'lot' => [
        'title' => 'Lots',
        'subtitle' => 'Lot stock, split and merge',
        'empty' => 'No lots yet.',
        'lot_no' => 'Lot',
        'article' => 'Article',
        'best_before' => 'Best before',
        'on_hand' => 'On hand',
        'split' => 'Split',
        'merge' => 'Merge',
        'new_lot_no' => 'New lot',
        'qty' => 'Quantity',
        'from' => 'From',
        'into' => 'Into',
        'flash' => [
            'split' => 'Lot split.',
            'merged' => 'Lots merged.',
            'unknown' => 'Unknown lot.',
        ],
    ],

    'label_template' => [
        'title' => 'Label templates',
        'subtitle' => 'Layout, paper size, QR and fields per template',
        'add' => 'New template',
        'empty' => 'No label templates.',
        'name' => 'Name',
        'paper_size' => 'Paper size',
        'orientation' => 'Orientation',
        'orientation_landscape' => 'Landscape',
        'orientation_portrait' => 'Portrait',
        'with_qr' => 'QR code',
        'is_default' => 'Default template',
        'default' => 'Default',
        'fields' => 'Fields',
        'delete' => 'Delete template',
        'field' => [
            'title' => 'Title',
            'subtitle' => 'Subtitle',
            'code' => 'Code',
            'code_type' => 'Code type',
            'lines' => 'Lines',
        ],
        'flash' => [
            'saved' => 'Template saved.',
            'deleted' => 'Template deleted.',
        ],
    ],
];
