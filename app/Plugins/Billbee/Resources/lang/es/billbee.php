<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billbee.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Billbee',
    'intro' => 'Pedidos multicanal de Billbee (Amazon, eBay, Otto, Kaufland, Shopify …): la importación es inbox-first y los clientes nunca se asignan a ciegas. Credenciales: tarjeta del plugin.',
    'field' => [
        'channel' => 'Canal',
        'state' => 'Estado',
        'order_number' => 'N.º de pedido',
        'buyer' => 'Comprador',
        'customer' => 'Cliente',
        'total' => 'Bruto',
        'ordered_at' => 'Pedido el',
    ],
    'filter' => [
        'all_channels' => 'Todos los canales',
        'apply' => 'Filtrar',
    ],
    'action' => [
        'sync' => 'Sincronizar ahora',
    ],
    'flash' => [
        'synced' => 'Sincronización de Billbee finalizada: :imported nuevos, :staged asignaciones abiertas.',
        'sync_failed' => 'La sincronización de Billbee falló; consulte el registro.',
    ],
    'open_inbox' => ':count asignaciones abiertas',
    'last_sync' => 'Última sincronización :at',
    'status' => [
        'open_assignment' => 'Asignación pendiente',
    ],
    'empty' => 'Aún no hay pedidos replicados.',
];
