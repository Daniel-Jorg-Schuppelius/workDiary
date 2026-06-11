<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Interfaz financiera',
        'transfers' => 'Justificantes de traspaso',
        'transfer' => 'Justificante de traspaso',
        'menu' => 'Entrega de facturación',
        'positions' => 'Posiciones generadas',
        'sources' => 'Fuentes individuales (instantánea)',
        'events' => 'Registro de eventos',
    ],

    'subtitle' => [
        'transfers' => 'Entregar tiempos y materiales facturables al sistema de facturación principal, en canales separados.',
    ],

    'field' => [
        'billing_mode' => 'Canal de facturación',
        'billing_mode_inherit' => '— Heredar el estándar de la organización —',
        'billing_mode_default' => '— WorkDiary (predeterminado) —',
        'billing_mode_hint' => 'Sustituye el estándar de la organización para este cliente. Con Lexoffice/DATEV la facturación local está bloqueada.',
        'billing_mode_org_hint' => 'Canal de facturación predeterminado de la organización. Los clientes pueden sustituirlo individualmente.',
        'channel' => 'Canal de traspaso',
        'target' => 'Destino del traspaso',
        'status' => 'Estado',
        'period' => 'Período de prestación',
        'position_count' => 'Posiciones',
        'total_amount' => 'Importe total (neto)',
        'total_quantity' => 'Cantidad total',
        'payload_hash' => 'Hash del payload',
        'transferred_at' => 'Traspasado el',
        'failure_reason' => 'Motivo del fallo',
        'customer' => 'Cliente',
        'source' => 'Fuente',
        'source_deleted' => 'Fuente ya no disponible',
    ],

    'action' => [
        'create_draft' => 'Preparar el traspaso',
        'confirm' => 'Confirmar el traspaso',
        'mark_transferred' => 'Marcar como traspasado',
        'mark_failed' => 'Marcar como fallido',
        'void' => 'Anular el traspaso',
        'show' => 'Mostrar',
        'execute' => 'Traspasar ahora',
        'retry' => 'Reintentar',
        'download' => 'Descargar el paquete de entrega',
        'open_external' => 'Abrir externamente',
    ],

    'filter' => [
        'all' => 'Todos',
    ],

    'hint' => [
        'channels_separate' => 'El tiempo y el material se confirman como paquetes de entrega separados.',
        'datev_desktop_api' => 'DATEV dirige: entrega como paquete de archivo (CSV) — la API DATEV Desktop seguirá como adaptador separado.',
        'target_by_mode' => 'El destino se preselecciona según el canal de facturación del cliente.',
        'period_sources' => 'Solo se recopilan fuentes facturables, aún no facturadas/entregadas en el período.',
        'lexoffice_draft_created' => 'Borrador de factura creado en Lexoffice:',
    ],

    'confirm_execute' => '¿Traspasar ahora al destino? Si tiene éxito, las fuentes se marcarán como entregadas.',
    'confirm_void' => '¿Anular este traspaso? Las fuentes se liberarán de nuevo.',

    'empty_title' => 'No hay justificantes de traspaso',
    'empty_message' => 'Aún no se ha preparado ningún traspaso.',
    'empty_filtered' => 'No hay traspasos para los filtros seleccionados.',
    'empty_positions_title' => 'No hay posiciones',
    'empty_positions' => 'Las fuentes no generan ninguna posición (p. ej. fuentes eliminadas).',

    'csv' => [
        'package_title' => 'Paquete de entrega WorkDiary (CSV) — no es un formato DATEV',
        'position' => 'Posición',
        'date' => 'Fecha',
        'employee' => 'Empleado',
        'project' => 'Proyecto/Pedido',
        'activity' => 'Actividad',
        'hours' => 'Horas',
        'rate' => 'Tarifa',
        'amount' => 'Importe',
        'comment' => 'Comentario',
        'product' => 'Producto',
        'quantity' => 'Cantidad',
        'unit' => 'Unidad',
        'unit_price_net' => 'Precio unitario neto',
        'total' => 'Total',
    ],

    'lexoffice' => [
        'introduction' => 'Entrega desde WorkDiary — :channel, período :from – :to.',
    ],

    'flash' => [
        'created' => 'Borrador del justificante de traspaso creado.',
        'confirmed' => 'Traspaso confirmado.',
        'transferred' => 'Traspaso completado — las fuentes se han marcado como traspasadas.',
        'failed' => 'Traspaso marcado como fallido.',
        'voided' => 'Traspaso anulado — las fuentes se han liberado de nuevo.',
    ],

    'error' => [
        'local_invoicing_locked' => 'La facturación la dirige :program; la creación local de facturas está bloqueada.',
        'no_sources' => 'No se han encontrado fuentes traspasables en el período seleccionado.',
        'illegal_transition' => 'El cambio de estado de «:from» a «:to» no está permitido.',
        'void_after_transfer' => 'Un traspaso ya entregado no puede anularse — utilice un traspaso de anulación/diferencia.',
        'entry_already_transferred' => 'El registro de tiempo ya se ha entregado a la facturación y no puede corregirse más.',
        'target_not_allowed' => 'Este destino no está permitido para el canal de facturación «:mode».',
        'lexoffice_not_configured' => 'Lexoffice no está configurado para esta organización (falta la clave API).',
        'sources_missing' => 'Las fuentes de este justificante de traspaso ya no están completamente disponibles.',
    ],
];
