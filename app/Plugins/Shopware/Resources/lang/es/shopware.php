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
        'description' => 'Lee los pedidos de una tienda Shopware 6 mediante una integración, los refleja en WorkDiary y asigna los compradores a clientes existentes; los compradores dudosos pasan a ser casos por aclarar. No se escribe nada en la tienda.',
    ],
    'settings' => [
        'base_url' => 'Dirección de la tienda',
        'base_url_help' => 'Dirección de la tienda, p. ej. https://shop.example.com (sin /api ni /admin).',
        'client_id' => 'ID de clave de acceso',
        'client_id_help' => 'En Shopware, cree una integración en Ajustes → Sistema → Integraciones. Necesita permiso de lectura sobre pedidos, clientes del pedido, clientes, líneas, transacciones de pago con capturas y reembolsos, monedas y estados, o la opción «Administrador».',
        'client_secret' => 'Clave de acceso secreta',
    ],
    'search' => [
        'domain' => 'Pedidos de Shopware',
    ],
    'cockpit' => [
        'title' => 'Pedidos de Shopware',
    ],
    'health' => [
        'not_configured' => 'Shopware no está configurado (falta la dirección de la tienda o la integración).',
        'not_connected' => 'La conexión con Shopware aún no se ha comprobado.',
    ],
];
