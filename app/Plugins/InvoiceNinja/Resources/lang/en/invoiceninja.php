<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoiceninja.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Mirrors invoices, credits and expenses from Invoice Ninja into the document flow, read-only and with a link to the original. Customers and suppliers are only linked via existing assignments, the customer number or the supplier name.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Leave empty for hosted Invoice Ninja; for your own installation its address, e.g. https://invoicing.example.com.',
        'api_token' => 'API token',
        'api_token_help' => 'In Invoice Ninja, create it under Settings → Account Management → API Tokens. Its user needs admin rights or the right to view invoices, credits and expenses – otherwise it only sees its own. The hosted Invoice Ninja offers the API only on paid plans.',
        'pull_vouchers' => 'Fetch documents',
        'pull_vouchers_help' => 'Only with this switch does the hourly fetch read documents. It writes nothing to Invoice Ninja.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an installation in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
    'health' => [
        'not_configured' => 'Invoice Ninja is not set up (API token missing).',
    ],
];
