<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : b2b_catalog.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// B2B catalog access (feature 099): OCI punchout outbound + openTRANS order intake.
return [
    'title' => 'B2B catalog access',
    'intro' => 'Your B2B customers\' e-procurement systems punch out into the released article catalog via OCI 4.0 and send orders back as openTRANS 2.1 ORDER.',
    'punchout_url' => 'Punchout URL (for the customer\'s procurement system)',

    'access_new_heading' => 'Issue new access',
    'access_new_hint' => 'One access per customer: username + secret for the OCI punchout. The secret is shown only once.',
    'access_heading' => 'Punchout accesses',
    'access_empty' => 'No accesses issued yet.',
    'access_title' => 'Access: :label',

    'new_secret_heading' => 'New punchout secret',
    'new_secret_hint' => 'Copy it now and store it in the customer\'s procurement system — the plain text is shown only this once.',

    'items_heading' => 'Released articles',
    'items_hint' => 'Only explicitly released articles are visible in the punchout. Without a customer price the default sale price applies.',
    'items_empty' => 'No articles released yet.',

    'orders_heading' => 'openTRANS orders',
    'orders_hint' => 'Orders (upload, mail or cloud intake) appear as suggestions in the assignment inbox; only booking creates the job.',
    'orders_empty' => 'No orders received yet.',

    'field' => [
        'customer' => 'Customer',
        'customer_placeholder' => '… select customer',
        'label' => 'Label',
        'username' => 'Username',
        'items_count' => 'Articles',
        'last_used' => 'Last used',
        'status' => 'Status',
        'actions' => 'Actions',
        'article' => 'Article',
        'article_placeholder' => '… select article',
        'article_number' => 'Article no.',
        'article_name' => 'Article',
        'default_price' => 'Default price',
        'custom_price' => 'Customer price',
        'custom_price_placeholder' => 'Default',
        'order_id' => 'Order no.',
        'source' => 'Channel',
        'total_net' => 'Total net',
        'ordered_at' => 'Order date',
    ],

    'action' => [
        'datanorm' => 'Export DATPREIS',
        'issue' => 'Issue access',
        'manage' => 'Manage',
        'revoke' => 'Deactivate',
        'rotate' => 'Rotate secret',
        'back' => 'Back to overview',
        'release' => 'Release article',
        'remove' => 'Remove',
        'upload_order' => 'Upload order',
    ],

    'status' => [
        'active' => 'Active',
        'revoked' => 'Deactivated',
        'order_open' => 'Open (inbox)',
        'order_booked' => 'Booked',
        'order_dismissed' => 'Dismissed',
    ],

    'flash' => [
        'datanorm_empty' => 'No released articles with a price for this access.',
        'datanorm_revoked' => 'This access has been revoked — customer price lists are no longer exported.',
        'access_issued' => 'Access issued.',
        'access_rotated' => 'Secret rotated.',
        'access_revoked' => 'Access deactivated.',
        'item_released' => 'Article released.',
        'item_removed' => 'Release removed.',
        'order_received' => 'Order :id received — a suggestion is waiting in the assignment inbox.',
        'order_duplicate' => 'Order :id is already recorded (no change).',
    ],

    'error' => [
        'not_opentrans' => 'The file is not a readable openTRANS 2.1 ORDER: :reason',
        'customer_required' => 'Please select a customer.',
        'not_open' => 'The order is no longer open.',
    ],

    'order' => [
        'entry_title' => 'Order :id',
        'entry_intro' => 'openTRANS order :id (channel: :source).',
        'line_unmatched' => 'article not matched',
    ],

    'public' => [
        'title' => 'B2B catalog',
        'footer' => 'Punchout catalog — the cart is handed over to your procurement system; the order is placed through your own system.',
        'search_placeholder' => 'Article number or name …',
        'search' => 'Search',
        'empty' => 'No released articles found.',
        'col_number' => 'Article no.',
        'col_name' => 'Name',
        'col_unit' => 'Unit',
        'col_price' => 'Price',
        'col_quantity' => 'Quantity',
        'page_of' => 'Page :current of :last',
        'prev' => 'Previous',
        'next' => 'Next',
        'to_cart' => 'Transfer cart',
        'transfer_title' => 'Handover to the procurement system',
        'transfer_hint' => 'The cart is transferred to your procurement system. If the redirect does not start automatically, use the button.',
        'transfer_submit' => 'Transfer cart now',
        'error_title' => 'Catalog access',
        'error_hook_url' => 'Invalid HOOK_URL — only HTTPS addresses are allowed.',
        'error_credentials' => 'Invalid credentials or access deactivated.',
        'error_session' => 'The catalog session has expired. Please punch out again from your procurement system.',
        'error_empty_cart' => 'No positions with a quantity selected.',
    ],
];
