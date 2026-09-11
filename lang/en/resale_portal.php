<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_portal.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Customer portal "my subscriptions" (feature 152): holdings without prices, purchasing or vouchers.
return [
    'title' => 'My subscriptions',
    'menu' => 'Subscriptions',
    'subtitle' => 'Your subscriptions and licences — including those of your end customers. Prices and amounts are on your invoices.',
    'field' => [
        'product' => 'Name / product',
        'holder' => 'Holder',
        'quantity' => 'Quantity',
        'term' => 'Term',
        'interval' => 'Interval',
        'renewal' => 'Renewal',
        'next_period' => 'Next period',
        'status' => 'Status',
        'kind' => 'Type',
        'period' => 'Period',
    ],
    'holder' => [
        'end_customer' => 'End customer',
    ],
    'term' => [
        'since' => 'since :date',
        'range' => ':from – :to',
        'running' => 'running',
    ],
    'interval' => [
        'yearly' => 'yearly',
        'monthly' => 'monthly',
    ],
    'next_period' => [
        'none' => 'none further',
    ],
    'period_status' => [
        'open' => 'open',
        'billed' => 'invoiced',
        'partial' => 'partially invoiced',
        'waived' => 'not invoiced',
        'disputed' => 'under review',
    ],
    'periods' => [
        'title' => 'Billing periods',
        'hint' => 'Periods derive from start, term and interval; "invoiced" means you have received an invoice for it.',
        'empty' => 'No periods planned yet.',
    ],
    'empty' => 'No subscriptions on record.',
    'back' => 'Back to overview',
    'show' => 'Details',
];
