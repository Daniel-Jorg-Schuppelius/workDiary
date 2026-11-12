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
        'search' => 'Pedido o comprador',
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
    // Bereich der globalen Suche (Feature 181).
    'search' => [
        'domain' => 'Pedidos de Billbee',
    ],
    // Panel am Kunden-Cockpit (MVP-1370).
    'cockpit' => [
        'title' => 'Pedidos de Billbee',
    ],
    // Bestandsführung (MVP-1626)
    'inventory' => [
        'heading' => 'Gestión de existencias',
        'explain' => 'Con «Billbee», WorkDiary sigue registrando en su propio diario de almacén y comunica cada nivel como existencias absolutas por SKU a Billbee, que lo reparte a los marketplaces. «Local» no comunica nada. No hay modo de solo lectura: Billbee no lleva existencias propias.',
        'mode_local' => 'Local — WorkDiary gestiona las existencias, Billbee no recibe nada.',
        'mode_external' => 'Billbee — WorkDiary gestiona las existencias y comunica cada nivel a Billbee.',
        'save' => 'Guardar gestión de existencias',
        'confirm' => '¿Cambiar realmente la gestión de existencias?',
        'changed' => 'Gestión de existencias cambiada.',
        'unchanged' => 'La gestión de existencias ya está configurada así.',
        'needs_connection' => 'Faltan las credenciales para comunicar con Billbee en la configuración del plugin.',
        'other_leads' => 'Las existencias las gestiona actualmente :plugin. Ponga allí primero la gestión en local.',
    ],
];
