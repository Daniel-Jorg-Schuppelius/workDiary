<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shopware.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reads orders from a Shopware 6 shop via an integration, mirrors them in WorkDiary and assigns buyers to existing customers; unclear buyers become open cases. Nothing is written back to the shop.',
    ],
    'settings' => [
        'base_url' => 'Shop address',
        'base_url_help' => 'Address of the shop, e.g. https://shop.example.com (without /api or /admin).',
        'client_id' => 'Access key ID',
        'client_id_help' => 'In Shopware, create an integration under Settings → System → Integrations. It needs read permission on orders, order customers, customers, line items, payment transactions with captures and refunds, currencies and states – or the “Administrator” option.',
        'client_secret' => 'Secret access key',
    ],
    'search' => [
        'domain' => 'Shopware orders',
    ],
    'cockpit' => [
        'title' => 'Shopware orders',
    ],
    'health' => [
        'not_configured' => 'Shopware is not set up (shop address or integration missing).',
        'not_connected' => 'Shopware connection not checked yet.',
    ],
];
