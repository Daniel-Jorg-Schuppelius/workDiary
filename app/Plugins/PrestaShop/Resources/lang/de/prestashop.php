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
        'description' => 'Liest Bestellungen eines PrestaShop-Shops über den Webservice, spiegelt sie in WorkDiary und ordnet die Käufer vorhandenen Kunden zu; unklare Käufer werden Klärfälle. In den Shop wird nichts zurückgeschrieben.',
    ],
    'settings' => [
        'base_url' => 'Shop-Adresse',
        'base_url_help' => 'Adresse des Shops, z. B. https://shop.example.com (ohne /api).',
        'api_key' => 'Webservice-Schlüssel',
        'api_key_help' => 'In PrestaShop unter Erweiterte Parameter → Webservice den Webservice aktivieren und einen Schlüssel mit Leserecht (GET) auf orders, customers, currencies und order_states anlegen.',
        'shop_timezone' => 'Zeitzone des Shops',
        'shop_timezone_help' => 'PrestaShop liefert Zeiten ohne Zeitzone. Leer lassen, wenn der Shop in der Zeitzone Ihrer Organisation läuft; sonst z. B. Europe/Vienna.',
    ],
    'search' => [
        'domain' => 'PrestaShop-Bestellungen',
    ],
    'cockpit' => [
        'title' => 'PrestaShop-Bestellungen',
    ],
    'health' => [
        'not_configured' => 'PrestaShop ist nicht eingerichtet (Shop-Adresse oder Schlüssel fehlen).',
        'not_connected' => 'PrestaShop-Verbindung noch nicht geprüft.',
    ],
];
