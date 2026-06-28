<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : manufacturing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Fabricación',

    'capacity' => [
        'title' => 'Capacidad',
        'subtitle' => 'Centros de trabajo y carga (incl. preparación) en el periodo seleccionado',
        'day' => 'Día',
        'period_note' => 'Carga sobre el periodo de cabecera :from – :to (capacidad = capacidad diaria × días).',
        'add' => 'Nuevo centro de trabajo',
        'empty' => 'No hay centros de trabajo.',
        'work_center' => 'Centro de trabajo',
        'code' => 'Código',
        'capacity' => 'Capacidad',
        'planned' => 'Planificado',
        'free' => 'Libre',
        'utilization' => 'Utilización',
        'setup' => 'Tiempo de preparación',
        'assign' => 'Asignar centro de trabajo',
        'minutes' => 'Minutos',
        'flash' => [
            'created' => 'Centro de trabajo creado.',
            'assigned' => 'Centro de trabajo asignado.',
            'assign_failed' => 'Asignación no posible.',
        ],
    ],

    'planning' => [
        'title' => 'Planificación de producción',
        'subtitle' => 'Necesidades de material multinivel (MRP) e indicadores de calidad',
        'explode' => 'Calcular necesidades',
        'requirements' => 'Necesidades de material',
        'no_bom' => 'Sin lista de materiales.',
        'level' => 'Nivel',
        'source' => 'Origen',
        'make' => 'Fabricación',
        'buy' => 'Compra',
        'gross' => 'Bruto',
        'net' => 'Neto',
        'quality' => 'Indicadores de calidad',
        'yield' => 'Rendimiento',
        'scrap_rate' => 'Tasa de desecho',
        'rework_rate' => 'Tasa de retrabajo',
        'spc' => 'SPC (pasos de medición)',
        'measurement' => 'Medición',
        'out_of_spec' => 'Fuera de tolerancia',
    ],

    'procurement_mode' => [
        'in_house' => 'Fabricación propia',
        'purchase' => 'Compra',
        'subcontract' => 'Subcontratación',
    ],

    'quantity_kind' => [
        'fixed' => 'Cantidad fija',
        'per_unit' => 'Cantidad por unidad',
        'ratio' => 'Proporción (receta)',
    ],
    'delivery_note' => [
        'title' => 'Albarán',
        'date' => 'Fecha de entrega',
        'order' => 'Orden',
        'recipient' => 'Destinatario',
        'warehouse' => 'Almacén',
        'no_customer' => 'Sin cliente asignado',
        'footer_note' => 'Solo comprobante de entrega — no es una factura. Confirme la recepción.',
        'col' => [
            'sku' => 'N.º de artículo',
            'name' => 'Descripción',
            'qty' => 'Cantidad',
            'unit' => 'Unidad',
        ],
    ],
    'parameter_type' => [
        'number' => 'Número',
        'measure' => 'Medida (con unidad)',
        'choice' => 'Selección',
        'text' => 'Texto',
        'date' => 'Fecha',
        'bool' => 'Sí/No',
    ],
    'parameter' => [
        'error' => [
            'required' => 'Falta el parámetro obligatorio ":param".',
            'invalid' => 'El parámetro ":param" tiene un valor no válido.',
        ],
    ],

    'status' => [
        'draft' => 'Borrador',
        'released' => 'Liberado',
        'in_progress' => 'En curso',
        'waiting' => 'En espera',
        'blocked' => 'Bloqueado',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ],

    'facturation_status' => [
        'pending' => 'Pendiente',
        'handed_over' => 'Entregado',
        'invoiced' => 'Facturado',
        'failed' => 'Fallido',
        'not_required' => 'No requerido',
    ],

    'bom_override' => [
        'disable' => 'Desactivar',
        'override_qty' => 'Sustituir cantidad',
        'add' => 'Añadir',
    ],

    'substitute_status' => [
        'requested' => 'Solicitado',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
    ],

    'procurement_status' => [
        'open' => 'Abierto',
        'ordered' => 'Pedido',
        'closed' => 'Cerrado',
    ],

    'order' => [
        'title' => 'Órdenes de fabricación',
        'subtitle' => 'Planificar, liberar y notificar órdenes de fabricación/montaje.',
        'empty' => 'Aún no hay órdenes de fabricación.',
        'action' => [
            'create' => 'Crear orden',
            'release' => 'Liberar',
            'start' => 'Iniciar',
            'reserve' => 'Reservar material',
            'report' => 'Notificar',
            'deliver' => 'Entregar',
            'push_lexoffice' => 'Enviar a Lexoffice',
            'subcontract' => 'Subcontratar',
            'cancel' => 'Cancelar',
        ],
        'field' => [
            'target_qty' => 'Cantidad objetivo',
            'good' => 'Cantidad buena',
            'scrap' => 'Desecho',
            'rework' => 'Retrabajo',
            'produced' => 'Producido',
            'quantity' => 'Cantidad',
            'materials' => 'Material',
            'reports' => 'Notificaciones',
            'article' => 'Artículo',
            'deliveries' => 'Entregas',
            'facturation_status' => 'Estado de facturación',
        ],
        'flash' => [
            'created' => 'Orden creada.',
            'released' => 'Orden liberada.',
            'started' => 'Orden iniciada.',
            'reserved' => 'Material reservado.',
            'reported' => 'Notificación registrada.',
            'delivered' => 'Entregado.',
            'lexoffice_pushed' => 'Albarán enviado a Lexoffice.',
            'subcontracted' => 'Asignado al proveedor (pedido creado).',
            'subcontract_failed' => 'Subcontratación no posible.',
            'cancelled' => 'Orden cancelada.',
            'deliver_needs_variant_warehouse' => 'La entrega requiere una variante y un almacén.',
        ],
    ],
];
