<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : akaunting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Mirrors invoices and bills from Akaunting into the document flow, read-only and with a link to the original. Suppliers are linked via existing assignments or their name.',
    ],
    'settings' => [
        'base_url' => 'Address',
        'base_url_help' => 'Address of the Akaunting installation, e.g. https://accounting.example.com (without /api).',
        'email' => 'E-mail',
        'email_help' => 'Sign-in of an Akaunting user with the “read API” permission and read permission on invoices and bills.',
        'password' => 'Password',
        'company_id' => 'Company ID',
        'company_id_help' => 'Number of the company in Akaunting; it appears in the address of the interface (e.g. /1/…).',
        'pull_vouchers' => 'Fetch documents',
        'pull_vouchers_help' => 'Only with this switch does the hourly fetch read documents. It writes nothing to Akaunting.',
        'allow_private_network' => 'Allow private addresses',
        'allow_private_network_help' => 'Only for an installation in your own network: allows a private or internal address. Logged, and only effective if the operator permits it.',
    ],
    'health' => [
        'not_configured' => 'Akaunting is not set up (address or credentials missing).',
    ],
];
