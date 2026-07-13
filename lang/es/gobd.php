<?php

/*
 * Entrega de datos GoBD Z3 (Feature 063, MVP-132).
 */

return [
    'title' => 'Entrega de datos GoBD (Z3)',
    'subtitle' => 'Datos fiscalmente relevantes como paquete GDPdU para la inspección fiscal (legible por IDEA).',
    'period' => 'Periodo de inspección',
    'sections' => 'Áreas de datos',
    'section' => [
        'invoices' => 'Facturas emitidas',
        'invoice_items' => 'Líneas de factura',
        'customers' => 'Deudores',
        'time_entries' => 'Registros de tiempo',
        'booking_batches' => 'Lotes contables',
        'booking_batch_items' => 'Posiciones de lotes contables',
        'payment_allocations' => 'Asignaciones de pago',
        'expenses' => 'Gastos',
    ],
    'preflight' => [
        'title' => 'Comprobación previa',
        'check' => 'Comprobar periodo',
        'records' => ':count registros',
        'warnings' => 'Avisos',
        'drafts' => ':count factura(s) no consolidada(s) (borrador) en el periodo — aún no definitivas fiscalmente.',
        'draft_batches' => ':count lote(s) contable(s) no consolidado(s) (borrador) en el periodo — falta(n) en la evidencia de lotes contables.',
        'empty_invoices' => 'No hay facturas emitidas en el periodo seleccionado.',
    ],
    'export' => 'Descargar paquete Z3',
    'recent' => [
        'title' => 'Exportaciones recientes',
        'package_hash' => 'Hash del paquete (SHA-256)',
        'records' => 'Registros',
        'created' => 'Creado',
        'none' => 'Aún no hay exportaciones.',
    ],
    'encoding' => 'Juego de caracteres de los archivos de datos',
];
