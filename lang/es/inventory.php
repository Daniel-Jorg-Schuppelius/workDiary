<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Inventario',

    'mode' => [
        'local' => 'Local (WorkDiary gestiona el stock)',
        'external' => 'Externo (lo gestiona el sistema de almacén)',
        'read_only' => 'Solo lectura (gestionado externamente)',
    ],

    'state' => [
        'physical' => 'Físico',
        'reserved' => 'Reservado',
        'blocked' => 'Bloqueado',
        'quality' => 'Control de calidad',
        'damaged' => 'Dañado',
        'scrap' => 'Desecho',
    ],

    'ownership' => [
        'own' => 'Stock propio',
        'customer' => 'Material del cliente',
        'consignment' => 'Consignación',
        'supplier' => 'Material del proveedor',
        'project' => 'Vinculado al proyecto',
    ],

    'movement' => [
        'receipt' => 'Entrada de mercancía',
        'issue' => 'Salida',
        'return' => 'Devolución',
        'transfer_out' => 'Traslado (salida)',
        'transfer_in' => 'Traslado (entrada)',
        'reserve' => 'Reserva',
        'release_reservation' => 'Reserva liberada',
        'scrap' => 'Desecho',
        'correction' => 'Corrección/diferencia de inventario',
        'finished_good_receipt' => 'Entrada de producto terminado',
    ],

    'warehouses' => 'Almacenes',
    'stock' => 'Stock',
    'subtitle' => [
        'warehouses' => 'Gestionar los almacenes del inquilino.',
        'stock' => 'Disponibilidad y movimientos por almacén.',
    ],
    'action' => [
        'create_warehouse' => 'Crear almacén',
        'edit_warehouse' => 'Editar almacén',
        'book' => 'Registrar movimiento',
    ],
    'field' => [
        'code' => 'Código',
        'default' => 'Predeterminado',
        'available' => 'Disponible',
        'physical' => 'Físico',
        'reserved' => 'Reservado',
        'location_note' => 'Nota de ubicación',
        'warehouse' => 'Almacén',
        'variant' => 'Variante',
        'quantity' => 'Cantidad',
        'movement' => 'Movimiento',
        'ownership' => 'Tipo de propiedad',
        'allow_negative' => 'Permitir stock negativo',
    ],
    'empty' => [
        'warehouses' => 'Aún no se han creado almacenes.',
        'stock' => 'No hay movimientos en este almacén.',
        'no_selection' => 'Ningún almacén seleccionado.',
    ],
    'confirm' => [
        'delete_warehouse' => '¿Eliminar realmente este almacén? Solo posible sin movimientos.',
    ],
    'flash' => [
        'warehouse_created' => 'Almacén creado.',
        'warehouse_updated' => 'Almacén actualizado.',
        'warehouse_deleted' => 'Almacén eliminado.',
        'warehouse_delete_blocked' => 'No se puede eliminar el almacén: existen movimientos.',
        'movement_posted' => 'Movimiento registrado.',
    ],
    'reservation_status' => [
        'active' => 'Activa',
        'fulfilled' => 'Cumplida',
        'released' => 'Liberada',
        'cancelled' => 'Cancelada',
    ],
    'count_status' => [
        'counting' => 'Conteo',
        'review' => 'Revisión',
        'completed' => 'Completada',
        'cancelled' => 'Cancelada',
    ],
    'count_ui' => [
        'title' => 'Inventario',
        'open' => 'Abrir inventario',
        'save' => 'Guardar conteo',
        'apply' => 'Contabilizar diferencias',
        'book' => 'Teórico',
        'counted' => 'Contado',
        'difference' => 'Diferencia',
        'counted_at' => 'Fecha de conteo',
        'no_counts' => 'Aún no hay inventarios para este almacén.',
        'no_selection' => 'Ningún almacén seleccionado.',
        'opened' => 'Inventario abierto, stock teórico congelado.',
        'saved' => 'Cantidades contadas guardadas.',
        'applied' => 'Diferencias contabilizadas como correcciones.',
        'cycle' => 'Ciclo (ABC)',
        'cycle_open' => 'Contar ciclo',
        'cycle_empty' => 'No hay artículos pendientes en esta clase.',
    ],
    'overview' => [
        'avg' => 'Coste medio',
        'value' => 'Valor',
        'priority' => 'Prioridad',
        'min_stock' => 'Stock mínimo',
        'reorder_point' => 'Punto de pedido',
        'release' => 'Liberar',
        'set_levels' => 'Definir niveles',
        'reservations' => 'Reservas',
        'below_reorder' => 'Necesidades de aprovisionamiento',
        'shortfall' => 'Faltante',
        'no_reservations' => 'Sin reservas activas.',
        'reservation_released' => 'Reserva liberada.',
        'levels_saved' => 'Niveles mín./pedido guardados.',
    ],

    'serial' => [
        'title' => 'Números de serie',
        'subtitle' => 'Ciclo de vida por unidad, prueba de envío y verificación de autenticidad.',
        'empty' => 'Aún no hay números de serie.',
        'blocked_default' => 'Bloqueado',
        'status' => [
            'created' => 'Creado',
            'in_stock' => 'En stock',
            'reserved' => 'Reservado',
            'shipped' => 'Enviado',
            'returned' => 'Devuelto',
            'blocked' => 'Bloqueado',
            'scrapped' => 'Desechado',
        ],
        'source' => [
            'manufactured' => 'Fabricación propia',
            'purchased' => 'Compra',
        ],
        'field' => [
            'serial_no' => 'Número de serie',
            'status' => 'Estado',
            'source' => 'Origen',
            'article' => 'Artículo',
            'variant' => 'Variante',
            'warehouse' => 'Almacén',
            'customer' => 'Cliente',
            'order' => 'Orden de fabricación',
            'delivery' => 'Entrega',
            'shipped_at' => 'Enviado el',
            'reason' => 'Motivo',
        ],
        'action' => [
            'block' => 'Bloquear',
            'unblock' => 'Desbloquear',
            'scrap' => 'Desechar',
            'verify' => 'Pasaporte del dispositivo',
            'search' => 'Buscar',
        ],
        'flash' => [
            'blocked' => 'Número de serie bloqueado.',
            'unblocked' => 'Número de serie desbloqueado.',
            'scrapped' => 'Número de serie desechado.',
        ],
        'verify' => [
            'title' => 'Pasaporte del dispositivo / verificación de autenticidad',
            'subtitle' => 'Introduzca un número de serie para comprobar su estado y origen.',
            'placeholder' => 'Número de serie …',
            'not_found' => 'No se encontró ningún número de serie: autenticidad no confirmada.',
            'found' => 'Número de serie encontrado.',
        ],
    ],

    'conflict' => [
        'title' => 'Conflictos de existencias (externo)',
        'empty' => 'No hay conflictos de existencias abiertos.',
        'filter' => ['open' => 'Abiertos', 'all' => 'Todos'],
        'col' => [
            'id' => 'Movimiento',
            'operation' => 'Operación',
            'qty' => 'Cantidad',
            'status' => 'Estado',
            'actions' => 'Acciones',
        ],
        'status' => [
            'open' => 'Abierto',
            'resolved_local' => 'Mantenido local',
            'resolved_remote' => 'Tomado externo',
            'compensated' => 'Compensado',
            'dismissed' => 'Descartado',
        ],
        'action' => [
            'compensate' => 'Contraasiento',
            'keep_local' => 'Mantener local',
        ],
        'flash' => [
            'kept_local' => 'Conflicto cerrado — se mantienen las existencias locales.',
            'compensated' => 'Conflicto compensado — contraasiento registrado.',
        ],
    ],

    'outbox' => [
        'status' => [
            'pending' => 'Pendiente',
            'processing' => 'En entrega',
            'confirmed' => 'Confirmado',
            'failed' => 'Fallido',
            'compensation_required' => 'Compensación necesaria',
        ],
    ],

    'valuation' => [
        'method' => [
            'moving_average' => 'Promedio ponderado',
            'fifo' => 'FIFO',
            'fefo' => 'FEFO (primero en caducar)',
        ],
    ],

    'scan' => [
        'action' => [
            'receipt' => 'Entrada de mercancía',
            'issue' => 'Salida',
            'transfer' => 'Traslado',
        ],
        'title' => 'Escanear',
        'subtitle' => 'Escanea un código y registra',
        'code' => 'Código',
        'qty' => 'Cantidad',
        'book' => 'Registrar',
        'action_label' => 'Acción',
        'target' => 'Almacén destino',
        'invalid' => 'Entrada no válida.',
        'booked' => 'Movimiento registrado.',
    ],

    'lot' => [
        'title' => 'Lotes',
        'subtitle' => 'Existencias por lote, división y fusión',
        'empty' => 'No hay lotes.',
        'lot_no' => 'Lote',
        'article' => 'Artículo',
        'best_before' => 'Consumo preferente',
        'on_hand' => 'Existencias',
        'split' => 'Dividir',
        'merge' => 'Fusionar',
        'new_lot_no' => 'Nuevo lote',
        'qty' => 'Cantidad',
        'from' => 'De',
        'into' => 'A',
        'flash' => [
            'split' => 'Lote dividido.',
            'merged' => 'Lotes fusionados.',
            'unknown' => 'Lote desconocido.',
        ],
    ],

    'label_template' => [
        'title' => 'Plantillas de etiqueta',
        'subtitle' => 'Diseño, tamaño, QR y campos por plantilla',
        'add' => 'Nueva plantilla',
        'empty' => 'No hay plantillas de etiqueta.',
        'name' => 'Nombre',
        'paper_size' => 'Tamaño',
        'orientation' => 'Orientación',
        'orientation_landscape' => 'Horizontal',
        'orientation_portrait' => 'Vertical',
        'with_qr' => 'Código QR',
        'is_default' => 'Plantilla predeterminada',
        'default' => 'Predeterminada',
        'fields' => 'Campos',
        'delete' => 'Eliminar plantilla',
        'field' => [
            'title' => 'Título',
            'subtitle' => 'Subtítulo',
            'code' => 'Código',
            'code_type' => 'Tipo de código',
            'lines' => 'Líneas',
        ],
        'flash' => [
            'saved' => 'Plantilla guardada.',
            'deleted' => 'Plantilla eliminada.',
        ],
    ],
];
