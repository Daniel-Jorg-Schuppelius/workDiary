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
            'dun_confirm' => '¿Crear un aviso de pago en la contabilidad?',
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
            'overdue' => 'Vencido',
            'overdue_count' => '{0} ningún documento|{1} :count documento|[2,*] :count documentos',
            'neutral' => 'Sin efecto monetario',
            'neutral_hint' => 'Presupuestos, confirmaciones de pedido y albaranes solo se cuentan.',
        ],
        'filter' => [
            'direction' => 'Sentido',
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
        ],
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
];
