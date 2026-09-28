<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : platform_usage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Nutzung je Mandant und Branchenvergleich (MVP-951/949).
return [
    'title' => 'Usage per tenant',
    'subtitle' => 'Users, storage, modules and last activity per organisation — platform operators only.',
    'back' => 'Organisations',
    'empty' => 'No organisations found.',
    'field' => [
        'organization' => 'Organisation',
        'status' => 'Status',
        'users' => 'Users',
        'active_users' => 'Active (30 days)',
        'storage' => 'Storage',
        'modules' => 'Modules',
        'last_activity' => 'Last activity',
    ],
    'benchmark' => [
        'link' => 'Industry comparison',
        'subtitle' => 'Annual emissions per main industry profile across all tenants excluding demos, only with at least three organisations per industry.',
        'title' => 'Emissions by industry :year (anonymous)',
        'branch' => 'Industry',
        'organizations' => 'Organisations',
        'mean' => 'Mean',
        'median' => 'Median',
        'empty' => 'No industry with at least :min organisations and recorded emissions.',
    ],
    // Nutzungsabrechnung, Abrechnungsdaten und Tarifanfragen (MVP-956/957).
    'billing' => [
        'title' => 'Usage billing',
        'subtitle' => 'Monthly usage per organisation, priced with the unit prices from the system settings (platform_billing.*). No invoice is created.',
        'month' => 'Month',
        'plan' => 'Plan',
        'amount' => 'Amount',
        'empty' => 'No monthly usage yet. It is recorded on the first of each month (platform:usage-snapshot).',
        'note' => 'Amount = base fee + users + active users + started GB of storage, each times the unit price.',
    ],
    'plan' => [
        'free' => 'Free',
        'pro' => 'Pro',
        'enterprise' => 'Enterprise',
    ],
    'billing_profile' => [
        'title' => 'Billing details',
        'subtitle' => 'Invoice recipient for the use of the software and plan changes.',
        'contact' => 'Invoice recipient',
        'save' => 'Save',
        'invalid_vat' => 'The VAT ID is invalid.',
        'field' => [
            'name' => 'Name / company',
            'email' => 'Invoice e-mail',
            'street' => 'Street',
            'zip' => 'Postcode',
            'city' => 'City',
            'country' => 'Country (ISO)',
            'vat_id' => 'VAT ID',
            'reference' => 'Purchase order reference',
        ],
        'hint' => [
            'reference' => 'Appears on the operator’s invoices.',
        ],
        'flash' => [
            'saved' => 'Billing details saved.',
        ],
    ],
    'plan_request' => [
        'title' => 'Request a plan change',
        'open_title' => 'Open plan requests',
        'history' => 'Requests',
        'current' => 'Current plan: :plan',
        'send' => 'Send request',
        'withdraw' => 'Withdraw',
        'done' => 'Done',
        'decline' => 'Decline',
        'empty' => 'No requests.',
        'already_open' => 'There is already an open request.',
        'field' => [
            'plan' => 'Requested plan',
            'addons' => 'Add-on modules',
            'note' => 'Note',
            'requester' => 'Requested by',
            'created_at' => 'Date',
        ],
        'hint' => [
            'addons' => 'Module codes separated by commas, e.g. module.rental',
        ],
        'status' => [
            'open' => 'Open',
            'done' => 'Done',
            'declined' => 'Declined',
            'withdrawn' => 'Withdrawn',
        ],
        'flash' => [
            'sent' => 'Request sent. The operator will get back to you.',
            'withdrawn' => 'Request withdrawn.',
            'decided' => 'Request closed.',
        ],
    ],
];
