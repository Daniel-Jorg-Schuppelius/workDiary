<?php
/*
 * Created on   : Tue Aug 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : etsy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Etsy',
    'intro' => 'Conexión directa de la tienda Etsy (Open API v3): los pedidos se reflejan inbox-first, la asignación de clientes nunca es a ciegas. Credenciales de la seller app propia de la organización: tarjeta del plugin.',
    'connection' => [
        'active' => 'Conectado: :shop',
        'none' => 'Sin conexión',
        'connect' => 'Conectar con Etsy',
        'disconnect' => 'Desconectar',
        'disconnect_confirm' => '¿Desconectar Etsy de verdad? Las filas espejo y las asignaciones se conservan.',
        'shop_pending' => 'Búsqueda de tienda pendiente',
        'shop_conflict' => 'Esta tienda de Etsy ya está conectada a otra organización.',
        'not_configured' => 'Primero guarde keystring/shared secret en la tarjeta del plugin.',
    ],
    'setup' => [
        'callback' => 'Redirect URI de la seller app (exacta, HTTPS):',
        'webhook' => 'URL de webhook para el portal de Etsy (eventos order.*):',
    ],
    'field' => [
        'receipt' => 'Pedido',
        'status' => 'Estado',
        'buyer' => 'Comprador',
        'customer' => 'Cliente',
        'total' => 'Bruto',
        'ordered_at' => 'Pedido el',
        'shipping' => 'Envío',
        'tracking_code' => 'N.º de seguimiento',
        'carrier' => 'Transportista',
    ],
    'filter' => [
        'all_statuses' => 'Todos los estados',
        'apply' => 'Filtrar',
    ],
    'action' => [
        'sync' => 'Sincronizar ahora',
        'ship' => 'Notificar envío',
        'ship_submit' => 'Notificar',
    ],
    'status' => [
        'open_assignment' => 'Asignación abierta',
        'guest' => 'Compra de invitado',
        'shipped' => 'Enviado',
    ],
    'flash' => [
        'synced' => 'Sincronización de Etsy terminada: :imported nuevos, :staged asignaciones abiertas.',
        'sync_failed' => 'La sincronización de Etsy falló — detalles en el registro.',
        'already_shipped' => 'El pedido ya está notificado como enviado.',
        'ship_queued' => 'Notificación de envío en cola — se avisará a Etsy.',
    ],
    'ledger' => [
        'caption' => 'Comisiones y pagos de los últimos 90 días (ledger de pagos de Etsy).',
        'type' => 'Tipo',
        'amount' => 'Suma',
        'entries' => 'Entradas',
    ],
    'open_inbox' => ':count asignaciones abiertas',
    'last_sync' => 'Última sincronización :at',
    'empty' => 'Aún no hay pedidos reflejados.',
];
