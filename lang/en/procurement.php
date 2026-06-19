<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Purchase orders',
    'subtitle' => 'Orders, goods receipt and reorder suggestions',
    'empty' => 'No purchase orders yet.',

    'action' => [
        'create' => 'New purchase order',
        'add_line' => 'Add line',
        'submit' => 'Place order',
        'receive' => 'Receive goods',
        'cancel' => 'Cancel',
        'suggestions' => 'Reorder suggestions',
        'apply' => 'Create orders',
        'incoming' => 'Expected receipts',
    ],

    'field' => [
        'number' => 'No.',
        'supplier' => 'Supplier',
        'warehouse' => 'Warehouse',
        'ordered_qty' => 'Ordered',
        'received_qty' => 'Received',
        'unit_price' => 'Unit price',
        'article' => 'Article',
        'qty' => 'Quantity',
        'expected_at' => 'Delivery date',
        'note' => 'Note',
    ],

    'flash' => [
        'created' => 'Purchase order created.',
        'line_added' => 'Line added.',
        'ordered' => 'Order placed.',
        'received' => 'Goods receipt booked.',
        'cancelled' => 'Order cancelled.',
        'suggestions_applied' => ':count order(s) created.',
        'unknown_article' => 'Unknown article.',
        'unknown_line' => 'Unknown line.',
        'no_warehouse' => 'No warehouse selected.',
    ],

    'ui' => [
        'suggestions_title' => 'Reorder suggestions',
        'needed' => 'Needed',
        'suggested' => 'Suggested',
        'none' => 'No suggestions.',
        'select_warehouse' => 'Select warehouse',
        'incoming_title' => 'Expected receipts',
        'incoming_subtitle' => 'Open lines of placed orders',
        'incoming_none' => 'No expected receipts.',
        'open' => 'Open',
    ],

    'status' => [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partially_received' => 'Partially received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'advice_status' => [
        'announced' => 'Announced',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ],

    'advice' => [
        'title' => 'Shipping notices',
        'announce' => 'Add shipping notice',
        'reference' => 'Notice / delivery no.',
        'announced_qty' => 'Announced',
        'receive' => 'Book goods receipt',
        'flash' => [
            'announced' => 'Shipping notice added.',
            'received' => 'Goods receipt booked from notice.',
            'cancelled' => 'Shipping notice cancelled.',
        ],
    ],
];
