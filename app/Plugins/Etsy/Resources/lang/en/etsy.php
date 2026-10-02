<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : etsy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Etsy',
    'intro' => 'Direct Etsy shop connection (Open API v3) — orders mirror inbox-first, customer assignment is never blind. Credentials of the org-owned seller app: plugin card.',
    'connection' => [
        'active' => 'Connected: :shop',
        'none' => 'Not connected',
        'connect' => 'Connect to Etsy',
        'disconnect' => 'Disconnect',
        'disconnect_confirm' => 'Really disconnect Etsy? Mirror rows and assignments are kept.',
        'shop_pending' => 'Shop lookup pending',
        'shop_conflict' => 'This Etsy shop is already connected to another organization.',
        'not_configured' => 'First store keystring/shared secret on the plugin card.',
    ],
    'setup' => [
        'callback' => 'Seller app redirect URI (exact, HTTPS):',
        'webhook' => 'Webhook URL for the Etsy portal (order.* events):',
    ],
    'field' => [
        'receipt' => 'Order',
        'status' => 'Status',
        'buyer' => 'Buyer',
        'customer' => 'Customer',
        'total' => 'Gross',
        'ordered_at' => 'Ordered at',
        'shipping' => 'Shipping',
        'tracking_code' => 'Tracking no.',
        'carrier' => 'Carrier',
    ],
    'filter' => [
        'all_statuses' => 'All statuses',
        'apply' => 'Filter',
    ],
    'action' => [
        'sync' => 'Sync now',
        'ship' => 'Report shipment',
        'ship_submit' => 'Report',
    ],
    'status' => [
        'open_assignment' => 'Assignment open',
        'guest' => 'Guest order',
        'shipped' => 'Shipped',
    ],
    'flash' => [
        'synced' => 'Etsy sync finished: :imported new, :staged open assignments.',
        'sync_failed' => 'Etsy sync failed — see log for details.',
        'already_shipped' => 'Order is already reported as shipped.',
        'ship_queued' => 'Shipment report queued — Etsy will be notified.',
    ],
    'ledger' => [
        'caption' => 'Fees and payouts of the last 90 days (Etsy payment ledger).',
        'type' => 'Type',
        'amount' => 'Sum',
        'entries' => 'Entries',
    ],
    'open_inbox' => ':count open assignments',
    'last_sync' => 'Last sync :at',
    'empty' => 'No orders mirrored yet.',
];
