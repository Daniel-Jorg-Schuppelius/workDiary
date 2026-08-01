<?php
/*
 * Created on   : Sat Aug 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : print.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Imprenta / copistería (MVP-459, perfil sectorial druck-kopiershop).
return [
    'document_title' => 'Datos de impresión :number',

    'nav' => [
        'section' => 'Imprenta & copistería',
    ],

    'orders' => [
        'title' => 'Órdenes de impresión',
        'subtitle' => 'Recepción de datos, preflight, aprobación de impresión, producción, control de calidad y entrega — de forma reproducible en la orden de fabricación.',
        'detail_title' => 'Orden de impresión',
        'empty' => 'No hay órdenes de impresión en el periodo — las nuevas órdenes se crean con el diálogo.',
        'kpi' => [
            'open' => 'Órdenes de impresión abiertas',
        ],
        'action' => [
            'create' => 'Nueva orden de impresión',
            'create_submit' => 'Crear orden',
            'manufacturing' => 'Orden de fabricación',
            'bind_file' => 'Vincular archivo',
            'run_preflight' => 'Ejecutar preflight',
            'override' => 'Anular con motivo',
            'manual_preflight' => 'Guardar hallazgos manuales',
            'approve' => 'Conceder aprobación de impresión',
            'start_production' => 'Iniciar producción',
            'resume_production' => 'Reanudar producción',
            'quality_check' => 'Documentar CC',
            'issue' => 'Entregar',
            'cancel' => 'Anular',
        ],
    ],

    'section' => [
        'order' => 'Orden',
        'file' => 'Archivo de producción & preflight',
        'approval' => 'Aprobación de impresión & instantánea',
        'production' => 'Producción, CC & entrega',
    ],

    'field' => [
        'article' => 'Artículo/producto de impresión',
        'quantity' => 'Cantidad',
        'unit' => 'Unidad',
        'customer_optional' => 'Cliente (opcional)',
        'walk_in' => 'Cliente de paso (datos mínimos)',
        'due_at' => 'Fecha límite',
        'output_kind' => 'Modo de entrega',
        'files_retain_until' => 'Retención del archivo hasta',
        'preflight' => 'Preflight',
        'file' => 'Archivo',
        'file_hash' => 'Suma de verificación (SHA-256)',
        'file_bound_at' => 'Vinculado el',
        'preflight_provider' => 'Herramienta de comprobación',
        'preflight_at' => 'Comprobado el',
        'override_reason' => 'Motivo de la anulación',
        'manual_errors' => 'Errores (uno por línea)',
        'manual_warnings' => 'Advertencias (una por línea)',
        'approved_by' => 'Aprobado por',
        'approved_at' => 'Aprobado el',
        'approved_file_hash' => 'Suma de verificación aprobada',
        'machine' => 'Máquina',
        'without_machine' => 'sin vinculación de máquina',
        'production_started_at' => 'Inicio de producción',
        'qc_status' => 'Resultado CC',
        'qc_by' => 'CC por',
        'qc_note' => 'Nota CC',
        'issued_at' => 'Entregado el',
        'handover_name' => 'Entregado a',
        'handover_note' => 'Nota de entrega',
        'shipment' => 'Envío',
        'reason' => 'Motivo',
        'good_total' => 'Cantidad buena',
        'scrap_total' => 'Merma',
        'cancel_reason' => 'Motivo de anulación',
    ],

    'snapshot' => [
        'final_format' => 'Formato final',
        'pages' => 'Páginas',
        'orientation' => 'Orientación',
        'bleed_mm' => 'Sangrado (mm)',
        'safety_mm' => 'Margen de seguridad (mm)',
        'color_mode' => 'Color',
        'color_profile' => 'Perfil de color',
        'spot_colors' => 'Tintas planas',
        'material' => 'Material/soporte',
        'grammage' => 'Gramaje',
        'quantity' => 'Cantidad',
        'due_date' => 'Fecha límite',
        'finishing' => 'Acabado',
    ],

    'badge' => [
        'approval_stale' => 'Archivo modificado — aprobación inválida',
        'file_purged' => 'Archivo eliminado tras la retención',
    ],

    'qc' => [
        'passed' => 'Liberado',
        'rework' => 'Retrabajo',
        'blocked' => 'Bloqueado',
    ],

    'hint' => [
        'retention' => 'Al vencer solo se elimina el archivo del cliente — orden, instantánea y suma de verificación permanecen como prueba comercial.',
        'no_snapshot' => 'Aún sin aprobación de impresión — los parámetros se congelan como instantánea inmutable al aprobar.',
        'counter_minimal' => 'Venta en mostrador: no se requieren datos personales.',
    ],

    'flash' => [
        'created' => 'Orden de impresión creada.',
        'file_bound' => 'Archivo de producción vinculado (suma de verificación guardada).',
        'preflight_recorded' => 'Hallazgos del preflight guardados.',
        'preflight_overridden' => 'Preflight anulado con motivo.',
        'approved' => 'Aprobación de impresión concedida — instantánea congelada.',
        'production_started' => 'Producción en marcha.',
        'quality_checked' => 'Control de calidad documentado.',
        'issued' => 'Orden entregada.',
        'cancelled' => 'Orden anulada.',
    ],

    'preflight' => [
        'file_missing' => 'El archivo de producción no se encuentra en el almacenamiento.',
        'file_empty' => 'El archivo está vacío (0 bytes).',
        'mime_unexpected' => 'Tipo de archivo inesperado «:mime» — verificar para impresión.',
        'pdf_header_invalid' => 'El archivo se declara PDF pero no tiene un encabezado PDF válido.',
    ],

    'error' => [
        'order_already_specialized' => 'Ya existe una orden de impresión para esta orden de fabricación (1:1).',
        'order_closed' => 'La orden de impresión está cerrada — el archivo ya no puede cambiarse.',
        'document_mismatch' => 'Documento/versión incoherentes o no pertenecen a esta organización.',
        'file_required' => 'Vincule primero un archivo de producción.',
        'provider_unsupported' => 'La herramienta de comprobación no admite este tipo de archivo.',
        'override_only_failed' => 'Solo los errores bloqueantes del preflight pueden anularse.',
        'override_reason_required' => 'La anulación requiere un motivo.',
        'preflight_blocks_approval' => 'Preflight pendiente o fallido — aprobación solo tras la comprobación o una anulación motivada.',
        'parameter_required' => 'Falta un parámetro obligatorio: :parameter.',
        'approval_stale' => 'El archivo se modificó tras la aprobación — la orden vuelve a requerir comprobación/aprobación.',
        'machine_foreign' => 'La máquina no pertenece a esta organización.',
        'machine_inspection_overdue' => 'Máquina con inspección/calibración obligatoria vencida — inicio no permitido.',
        'qc_result_invalid' => 'Resultado CC no válido.',
        'invalid_transition' => 'Cambio de estado no permitido.',
        'invalid_transition_detail' => 'Cambio de estado no permitido: :from → :to.',
        'shipment_required' => 'La entrega por envío requiere un envío existente.',
        'handover_required' => 'La recogida requiere un justificante de entrega (nombre).',
        'cancel_reason_required' => 'La anulación requiere un motivo.',
        'file_missing_storage' => 'La versión del archivo no existe en el almacenamiento.',
    ],
];
