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
        'description' => 'Refleja en modo de solo lectura facturas, abonos y gastos de Invoice Ninja en el flujo de documentos, con enlace al original. Los clientes y proveedores solo se vinculan mediante asignaciones existentes, el número de cliente o el nombre del proveedor.',
    ],
    'settings' => [
        'base_url' => 'Dirección',
        'base_url_help' => 'Déjelo vacío para Invoice Ninja alojado; para su propia instalación, su dirección, p. ej. https://facturas.example.com.',
        'api_token' => 'Token de API',
        'api_token_help' => 'En Invoice Ninja, créelo en Ajustes → Gestión de cuenta → Tokens API. Su usuario necesita derechos de administrador o el permiso para ver facturas, abonos y gastos; si no, solo ve los propios. El Invoice Ninja alojado solo ofrece la API en planes de pago.',
        'pull_vouchers' => 'Obtener documentos',
        'pull_vouchers_help' => 'Solo con este ajuste la consulta horaria lee documentos. No escribe nada en Invoice Ninja.',
        'allow_private_network' => 'Permitir direcciones privadas',
        'allow_private_network_help' => 'Solo para una instalación en su propia red: permite una dirección privada o interna. Se registra y solo surte efecto si el operador lo permite.',
    ],
    'health' => [
        'not_configured' => 'Invoice Ninja no está configurado (falta el token de API).',
    ],
];
