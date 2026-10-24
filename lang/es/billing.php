<?php
/*
 * Created on   : Sun Aug 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : billing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'feed' => [
        'title' => 'Flujo de documentos',
        'subtitle' => 'Presupuestos, facturas, comprobantes y gastos en el periodo :range — ajustable con el filtro de fechas de la cabecera.',
        'empty' => 'No hay documentos en el periodo seleccionado',
        'search_placeholder' => 'Número, cliente, proveedor …',
        'days_short' => 'd',
        'dunning_level' => 'Nivel de reclamación :level',
        'action' => [
            'dun' => 'Reclamar',
            'open_original' => 'Abrir el original',
        ],
        'tab' => [
            'all' => 'Todos',
            'quotes' => 'Presupuestos',
            'outgoing' => 'Facturas de venta',
            'incoming' => 'Facturas de compra',
            'credits' => 'Abonos',
            'expenses' => 'Gastos',
            'other' => 'Otros',
        ],
        'kpi' => [
            'revenue' => 'Ingresos',
            'expense' => 'Gasto (externo)',
            'balance' => 'Saldo',
            'internal_mine' => 'Mis gastos',
            'internal_all' => 'Gastos (todos)',
            'internal_pending' => 'de ellos en revisión: :amount',
            'open' => 'Pendiente',
            'overdue' => 'de los cuales vencidos',
            'open_count' => '{0} ningún documento abierto|{1} :count documento abierto|[2,*] :count documentos abiertos',
            'overdue_count' => ':count de :total documentos',
            'neutral' => 'Sin efecto monetario',
            'neutral_hint' => 'Presupuestos, confirmaciones de pedido y albaranes solo se cuentan.',
        ],
        'filter' => [
            'direction' => 'Sentido',
            'project' => 'Proyecto',
            'origin' => 'Origen',
            'only_overdue' => 'Solo vencidos',
            'only_unlinked' => 'Solo sin comprobante',
            'with_archived' => 'Incluir archivados',
        ],
        'state' => [
            'draft' => 'Borrador',
            'open' => 'Pendiente',
            'paid' => 'Cerrado',
            'cancelled' => 'Anulado',
            'deleted' => 'Eliminado en el origen',
        ],
        'foreign_amount' => 'Importe en una segunda moneda',
        'scope' => [
            'mine' => 'Míos',
            'all' => 'Todos',
        ],
        'column' => [
            'kind' => 'Tipo',
            'origin' => 'Origen',
            'due' => 'Vence',
            'open' => 'Pendiente',
        ],
    ],
    'relations' => [
        'title' => 'Documentos relacionados (:count)',
        'column' => [
            'document' => 'Documento',
            'number' => 'Número',
            'date' => 'Fecha',
            'state' => 'Estado',
            'system' => 'Sistema',
            'relation' => 'Relación',
        ],
        'via' => 'a través de :document :number',
        'schedule' => 'Plan de facturación',
        'quote_version' => 'Oferta (versión :version)',
        'manufacturing_order' => 'Orden de fabricación',
        'transfer' => 'Traspaso (:channel)',
        'draft' => 'Borrador',
    ],
    // Albarán como documento propio (fase 145, MVP-1163).
    'delivery_note' => [
        'create' => 'Crear albarán',
        'edit' => 'Editar cantidades',
        'edit_hint' => 'Para una entrega parcial, ajuste la cantidad; 0 quita la posición de este albarán.',
        'details' => 'Entrega',
        'deliver' => 'Entregar',
        'cancel' => 'Anular',
        'cancel_confirm' => '¿Anular el albarán? En el programa de contabilidad se mantiene.',
        'delivered_on' => 'Entregado el :date',
        'to_order' => 'Orden de fabricación :number',
        'number_pending' => ':program asigna el número al entregar; hasta entonces el albarán lleva un número provisional.',
        'flash' => [
            'updated' => 'Albarán guardado.',
            'delivered' => 'Albarán :number entregado.',
            'cancelled' => 'Albarán anulado.',
            'issue_failed' => 'Entregado, pero el albarán :number sigue como borrador: :error',
        ],
        'error' => [
            'not_confirmed' => 'Un albarán solo se crea a partir de una confirmación de pedido confirmada.',
            'fully_delivered' => 'Todas las posiciones de la confirmación de pedido ya se han entregado.',
            'not_editable' => 'Solo se pueden editar borradores sin salida de almacén.',
            'not_draft' => 'Solo se pueden entregar borradores.',
            'empty' => 'El albarán no tiene posiciones.',
            'not_cancellable' => 'Este albarán no se puede anular. Corrija una salida de almacén a través del movimiento de almacén.',
            'not_delivered' => 'El albarán aún no se ha entregado.',
        ],
    ],
];
