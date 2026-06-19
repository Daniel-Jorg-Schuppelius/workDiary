<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : procurement.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Pedidos de compra',
    'subtitle' => 'Pedidos, entrada de mercancía y sugerencias de reposición',
    'empty' => 'No hay pedidos de compra.',

    'action' => [
        'create' => 'Nuevo pedido',
        'add_line' => 'Añadir línea',
        'submit' => 'Pedir',
        'receive' => 'Entrada de mercancía',
        'cancel' => 'Cancelar',
        'suggestions' => 'Sugerencias de pedido',
        'apply' => 'Crear pedidos',
        'incoming' => 'Entradas previstas',
    ],

    'field' => [
        'number' => 'N.º',
        'supplier' => 'Proveedor',
        'warehouse' => 'Almacén',
        'ordered_qty' => 'Pedido',
        'received_qty' => 'Recibido',
        'unit_price' => 'Precio unitario',
        'article' => 'Artículo',
        'qty' => 'Cantidad',
        'expected_at' => 'Fecha de entrega',
        'note' => 'Nota',
    ],

    'flash' => [
        'created' => 'Pedido creado.',
        'line_added' => 'Línea añadida.',
        'ordered' => 'Pedido realizado.',
        'received' => 'Entrada de mercancía registrada.',
        'cancelled' => 'Pedido cancelado.',
        'suggestions_applied' => ':count pedido(s) creado(s).',
        'unknown_article' => 'Artículo desconocido.',
        'unknown_line' => 'Línea desconocida.',
        'no_warehouse' => 'Ningún almacén seleccionado.',
    ],

    'ui' => [
        'suggestions_title' => 'Sugerencias de pedido',
        'needed' => 'Necesidad',
        'suggested' => 'Sugerido',
        'none' => 'Sin sugerencias.',
        'select_warehouse' => 'Seleccionar almacén',
        'incoming_title' => 'Entradas previstas',
        'incoming_subtitle' => 'Líneas abiertas de pedidos realizados',
        'incoming_none' => 'No hay entradas previstas.',
        'open' => 'Abierto',
    ],

    'status' => [
        'draft' => 'Borrador',
        'ordered' => 'Pedido',
        'partially_received' => 'Parcialmente recibido',
        'received' => 'Recibido',
        'cancelled' => 'Cancelado',
    ],

    'advice_status' => [
        'announced' => 'Anunciado',
        'received' => 'Recibido',
        'cancelled' => 'Cancelado',
    ],

    'advice' => [
        'title' => 'Avisos de entrega',
        'announce' => 'Registrar aviso de entrega',
        'reference' => 'N.º aviso / albarán',
        'announced_qty' => 'Anunciado',
        'receive' => 'Registrar entrada',
        'flash' => [
            'announced' => 'Aviso de entrega registrado.',
            'received' => 'Entrada registrada desde el aviso.',
            'cancelled' => 'Aviso de entrega cancelado.',
        ],
    ],
];
