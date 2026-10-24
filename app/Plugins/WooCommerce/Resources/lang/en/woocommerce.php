<?php
/*
 * Created on   : Sat Oct 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : woocommerce.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'plugin' => [
        'description' => 'Reads orders from a WooCommerce shop, mirrors them in WorkDiary and assigns buyers to existing customers; unclear buyers become open cases. Nothing is written back to the shop.',
    ],
    'settings' => [
        'base_url' => 'Shop address',
        'base_url_help' => 'Address of the WordPress shop with https, e.g. https://shop.example.com (without /wp-json).',
        'consumer_key' => 'Consumer key',
        'consumer_key_help' => 'Create a key with read permission in WooCommerce under Settings → Advanced → REST API.',
        'consumer_secret' => 'Consumer secret',
    ],
    'search' => [
        'domain' => 'WooCommerce orders',
    ],
    'cockpit' => [
        'title' => 'WooCommerce orders',
    ],
    'health' => [
        'not_configured' => 'WooCommerce is not set up (shop address or keys missing).',
        'not_connected' => 'WooCommerce connection not checked yet.',
    ],
];
