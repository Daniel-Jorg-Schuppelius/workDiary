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
        'description' => 'Liest Bestellungen eines WooCommerce-Shops, spiegelt sie in WorkDiary und ordnet die Käufer vorhandenen Kunden zu; unklare Käufer werden Klärfälle. In den Shop wird nichts zurückgeschrieben.',
    ],
    'settings' => [
        'base_url' => 'Shop-Adresse',
        'base_url_help' => 'Adresse des WordPress-Shops mit https, z. B. https://shop.example.com (ohne /wp-json).',
        'consumer_key' => 'Consumer Key',
        'consumer_key_help' => 'In WooCommerce unter Einstellungen → Erweitert → REST-API einen Schlüssel mit Leserecht anlegen.',
        'consumer_secret' => 'Consumer Secret',
    ],
    'search' => [
        'domain' => 'WooCommerce-Bestellungen',
    ],
    'cockpit' => [
        'title' => 'WooCommerce-Bestellungen',
    ],
    'health' => [
        'not_configured' => 'WooCommerce ist nicht eingerichtet (Shop-Adresse oder Schlüssel fehlen).',
        'not_connected' => 'WooCommerce-Verbindung noch nicht geprüft.',
    ],
];
