<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : prestashop.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reads orders from a PrestaShop shop via the webservice, mirrors them in WorkDiary and assigns buyers to existing customers; unclear buyers become open cases. Nothing is written back to the shop.',
    ],
    'settings' => [
        'base_url' => 'Shop address',
        'base_url_help' => 'Address of the shop, e.g. https://shop.example.com (without /api).',
        'api_key' => 'Webservice key',
        'api_key_help' => 'In PrestaShop, enable the webservice under Advanced Parameters → Webservice and create a key with read permission (GET) on orders, customers, currencies and order_states.',
        'shop_timezone' => 'Shop time zone',
        'shop_timezone_help' => 'PrestaShop delivers times without a time zone. Leave empty if the shop runs in your organization’s time zone; otherwise e.g. Europe/Vienna.',
    ],
    'search' => [
        'domain' => 'PrestaShop orders',
    ],
    'cockpit' => [
        'title' => 'PrestaShop orders',
    ],
    'health' => [
        'not_configured' => 'PrestaShop is not set up (shop address or key missing).',
        'not_connected' => 'PrestaShop connection not checked yet.',
    ],
];
