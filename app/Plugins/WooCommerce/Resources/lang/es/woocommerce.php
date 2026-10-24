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
        'description' => 'Lee los pedidos de una tienda WooCommerce, los refleja en WorkDiary y asigna los compradores a clientes existentes; los compradores dudosos pasan a ser casos por aclarar. No se escribe nada en la tienda.',
    ],
    'settings' => [
        'base_url' => 'Dirección de la tienda',
        'base_url_help' => 'Dirección de la tienda WordPress con https, p. ej. https://shop.example.com (sin /wp-json).',
        'consumer_key' => 'Clave de cliente (consumer key)',
        'consumer_key_help' => 'Créela en WooCommerce en Ajustes → Avanzado → API REST, con permiso de lectura.',
        'consumer_secret' => 'Secreto de cliente (consumer secret)',
    ],
    'search' => [
        'domain' => 'Pedidos de WooCommerce',
    ],
    'cockpit' => [
        'title' => 'Pedidos de WooCommerce',
    ],
    'health' => [
        'not_configured' => 'WooCommerce no está configurado (falta la dirección de la tienda o las claves).',
        'not_connected' => 'La conexión con WooCommerce aún no se ha comprobado.',
    ],
];
