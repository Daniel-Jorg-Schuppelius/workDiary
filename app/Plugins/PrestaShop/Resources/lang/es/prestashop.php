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
        'description' => 'Lee los pedidos de una tienda PrestaShop mediante el webservice, los refleja en WorkDiary y asigna los compradores a clientes existentes; los compradores dudosos pasan a ser casos por aclarar. No se escribe nada en la tienda.',
    ],
    'settings' => [
        'base_url' => 'Dirección de la tienda',
        'base_url_help' => 'Dirección de la tienda, p. ej. https://shop.example.com (sin /api).',
        'api_key' => 'Clave del webservice',
        'api_key_help' => 'En PrestaShop, active el webservice en Parámetros avanzados → Webservice y cree una clave con permiso de lectura (GET) sobre orders, customers, currencies y order_states.',
        'shop_timezone' => 'Zona horaria de la tienda',
        'shop_timezone_help' => 'PrestaShop entrega las horas sin zona horaria. Déjelo vacío si la tienda funciona en la zona horaria de su organización; si no, p. ej. Europe/Vienna.',
    ],
    'search' => [
        'domain' => 'Pedidos de PrestaShop',
    ],
    'cockpit' => [
        'title' => 'Pedidos de PrestaShop',
    ],
    'health' => [
        'not_configured' => 'PrestaShop no está configurado (falta la dirección de la tienda o la clave).',
        'not_connected' => 'La conexión con PrestaShop aún no se ha comprobado.',
    ],
];
