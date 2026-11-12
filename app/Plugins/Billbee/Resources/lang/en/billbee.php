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
        'search' => 'Order or buyer',
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
    // Bereich der globalen Suche (Feature 181).
    'search' => [
        'domain' => 'Billbee orders',
    ],
    // Panel am Kunden-Cockpit (MVP-1370).
    'cockpit' => [
        'title' => 'Billbee orders',
    ],
    // Bestandsführung (MVP-1626)
    'inventory' => [
        'heading' => 'Inventory management',
        'explain' => 'With “Billbee”, WorkDiary keeps booking in its own stock journal and reports every level as an absolute stock per SKU to Billbee, which distributes it to the marketplaces. “Local” reports nothing. There is no read-only mode: Billbee keeps no stock of its own.',
        'mode_local' => 'Local — WorkDiary manages stock, Billbee receives nothing.',
        'mode_external' => 'Billbee — WorkDiary manages stock and reports every level to Billbee.',
        'save' => 'Save inventory management',
        'confirm' => 'Really switch inventory management?',
        'changed' => 'Inventory management changed.',
        'unchanged' => 'Inventory management is already set this way.',
        'needs_connection' => 'The credentials for reporting to Billbee are missing in the plugin settings.',
        'other_leads' => 'Inventory is currently led by :plugin. Switch inventory management to local there first.',
    ],
];
