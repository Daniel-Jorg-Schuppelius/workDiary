<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billbee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Billbee',
    'intro' => 'Multichannel orders from Billbee (Amazon, eBay, Otto, Kaufland, Shopify …) — imports are inbox-first, customers are never assigned blindly. Credentials: plugin card.',
    'field' => [
        'channel' => 'Channel',
        'state' => 'State',
        'order_number' => 'Order no.',
        'buyer' => 'Buyer',
        'customer' => 'Customer',
        'total' => 'Gross',
        'ordered_at' => 'Ordered at',
    ],
    'filter' => [
        'all_channels' => 'All channels',
        'apply' => 'Filter',
    ],
    'action' => [
        'sync' => 'Sync now',
    ],
    'flash' => [
        'synced' => 'Billbee sync finished: :imported new, :staged open assignments.',
        'sync_failed' => 'Billbee sync failed — see log for details.',
    ],
    'open_inbox' => ':count open assignments',
    'last_sync' => 'Last sync :at',
    'status' => [
        'open_assignment' => 'Assignment open',
    ],
    'empty' => 'No orders mirrored yet.',
];
