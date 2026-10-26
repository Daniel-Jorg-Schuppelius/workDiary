<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work-queue.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Cola de trabajo',
    'subtitle' => 'Todo lo que debería tramitar ahora: aprobaciones, bandejas de entrada, tareas y recordatorios. Cada elemento se tramita en su propia página.',
    'filter' => [
        'category' => 'Área',
        'all_categories' => 'Todas las áreas',
        'source' => 'Origen',
        'all_sources' => 'Todos los orígenes',
        'mine' => 'Solo los asignados a mí',
    ],
    'column' => [
        'urgency' => 'Urgencia',
        'item' => 'Elemento',
        'source' => 'Origen',
        'due' => 'Vence',
        'count' => 'Cantidad',
        'assignment' => 'Responsabilidad',
    ],
    'assignment' => [
        'personal' => 'Asignado a usted',
        'shared' => 'Por su rol',
    ],
    'empty' => [
        'title' => 'Nada que hacer',
        'message' => 'En este momento no hay ningún elemento esperándole.',
    ],
    'source' => [
        'resale_plan' => 'Suscripciones y licencias: plantillas periódicas',
        'approval' => 'Cadenas de aprobación',
        'vacation_approval' => 'Solicitudes de vacaciones',
        'expense_approval' => 'Gastos',
        'month_closure' => 'Cierres mensuales',
        'price_change' => 'Cambios de precio',
        'learning_time' => 'Tiempo de formación',
        'integration_inbox' => 'Bandeja de asignación',
        'invoice_inquiry' => 'Consultas sobre facturas',
        'incoming_invoice' => 'Facturas recibidas',
        'open_issue' => 'Puntos abiertos',
        'assigned_entry' => 'Pedidos',
        'task' => 'Tareas de proyecto',
        'service_ticket' => 'Tickets de servicio',
        'draft_reminder' => 'Sus borradores',
        'chat_reminder' => 'Recordatorios de chat',
    ],
    'item' => [
        'vacation' => 'Solicitud de vacaciones de :name',
        'expense' => 'Gasto de :name',
        'month_closure' => 'Mes :period de :name',
        'price_change' => 'Cambio de precio: :article',
        'learning_time' => 'Tiempo de formación de :name',
        'integration_inbox' => 'Asignaciones abiertas: :source',
        'invoice_inquiry' => 'Consulta sobre la factura :number',
        'incoming_invoice' => [
            'assign' => 'Asignar facturas recibidas',
            'review' => 'Revisar facturas recibidas',
            'failed' => 'Transferencias fallidas',
            'duplicates' => 'Posibles entradas duplicadas',
        ],
        'entry_untitled' => 'Pedido sin título',
        'chat_reminder' => 'Recordatorio de chat',
    ],
    'detail' => [
        'days_open' => ':days días sin cerrar',
    ],
    'widget' => [
        'label' => 'Cola de trabajo',
        'description' => 'Los elementos abiertos más urgentes de todas las áreas.',
        'all' => 'Ver todo',
        'empty' => 'En este momento no hay ningún elemento esperándole.',
    ],
];
