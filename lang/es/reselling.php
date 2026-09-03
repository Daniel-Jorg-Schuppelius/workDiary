<?php
/*
 * Created on   : Thu Sep 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reselling.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Lizenz-Reselling-Abgleich (Feature 151, MVP-757).
return [
    'title' => [
        'menu' => 'Conciliación de licencias',
        'index' => 'Conciliación de reventa de licencias',
        'show' => 'Ejecución de la conciliación',
    ],
    'subtitle' => 'Comparar las suscripciones de marketplace (Telekom, Quality Hosting) con las facturas emitidas en Lexoffice: periodos faltantes, parciales o facturados por debajo del coste, más una comprobación de precios contra la lista de precios del revendedor.',
    'action' => [
        'new' => 'Nueva ejecución',
        'download' => 'CSV',
        'delete' => 'Eliminar',
        'refresh' => 'Actualizar',
        'back' => 'Volver al resumen',
    ],
    'dialog' => [
        'title' => 'Iniciar una nueva ejecución',
        'hint' => 'Se necesita al menos un archivo de exportación. La ejecución lee Lexoffice en segundo plano; con muchos clientes tarda algunos minutos.',
        'telekom' => 'Telekom Cloud Marketplace: purchases.csv',
        'qualityhosting' => 'Quality Hosting: exportación de contratos (.xlsx)',
        'pricelist' => 'Quality Hosting: lista de precios (.xlsx, opcional)',
        'map' => 'Archivo de asignación (opcional)',
        'map_hint' => 'Una línea por empresa: «Empresa;UUID del contacto Lexoffice» o «Empresa;customer:<Sqid>». Para todo lo que la ejecución no pueda asignar de forma inequívoca.',
        'reference' => 'Fecha de referencia',
        'reference_hint' => 'Los periodos iniciados hasta este día cuentan como vencidos.',
        'before' => 'Días antes del inicio del periodo',
        'after' => 'Días después del inicio del periodo',
        'window_hint' => 'Una factura pertenece a un periodo si su fecha cae dentro de esta ventana alrededor del inicio del periodo.',
        'submit' => 'Iniciar',
    ],
    'field' => [
        'created' => 'Iniciada', 'status' => 'Estado', 'sources' => 'Fuentes', 'reference' => 'Fecha de referencia',
        'periods' => 'Periodos', 'problems' => 'Señalados', 'open_fee' => 'Coste de compra abierto', 'unmapped' => 'Sin asignación',
        'window' => 'Ventana', 'files' => 'Archivos', 'by' => 'Por', 'error' => 'Error', 'price_flags' => 'Avisos de precio',
        'company' => 'Empresa', 'customer' => 'Cliente', 'contact' => 'Contacto Lexoffice', 'mapping' => 'Asignación', 'candidates' => 'Candidatos',
        'source' => 'Fuente', 'edition' => 'Edición', 'period' => 'Periodo', 'quantity' => 'Cantidad', 'purchase' => 'Compra',
        'vouchers' => 'Factura(s)', 'unit_net' => 'Neto por unidad', 'note' => 'Nota', 'succession' => 'Sucesión',
        'voucher' => 'Factura', 'date' => 'Fecha', 'position' => 'Línea', 'remaining' => 'Restante',
        'product' => 'Producto', 'term' => 'Duración', 'running' => 'Unidades activas', 'contract_price' => 'Compra (contrato)', 'list_price' => 'Compra (lista)',
        'uvp' => 'PVP recomendado', 'sales' => 'Venta (mediana, número)', 'sales_range' => 'Venta mín – máx', 'margin' => 'Margen vs lista',
        'telekom_from' => 'Telekom desde', 'telekom_to' => 'Telekom hasta', 'successor' => 'Contrato QH', 'successor_from' => 'QH desde',
        'valid_from' => 'Lista de precios válida desde',
    ],
    'status' => [
        'queued' => 'En cola',
        'running' => 'En ejecución',
        'done' => 'Terminada',
        'failed' => 'Fallida',
    ],
    'section' => [
        'summary' => 'Resumen', 'price' => 'Comprobación de precios', 'findings' => 'Periodos', 'mappings' => 'Asignación empresa marketplace → contacto Lexoffice',
        'extras' => 'Líneas Microsoft sin periodo vencido', 'successions' => 'Sucesiones Telekom → Quality Hosting', 'issues' => 'Notas de los archivos', 'errors' => 'Errores de lectura', 'files' => 'Archivos y opciones',
    ],
    'filter' => [
        'status' => 'Estado', 'problems' => 'Solo señalados', 'all' => 'Todos', 'company' => 'Empresa', 'all_companies' => 'Todas las empresas',
    ],
    'empty' => [
        'runs' => 'Aún no hay ejecuciones. Sube los archivos de exportación para iniciar la primera conciliación.', 'findings' => 'No hay periodos en esta selección.', 'price' => 'No hay contratos activos o no se subió una lista de precios.', 'mappings' => 'No hay empresas.', 'extras' => 'No hay líneas adicionales.', 'successions' => 'No se detectaron sucesiones.',
    ],
    'price_flag' => [
        'below_list' => 'por debajo del coste', 'below_uvp' => 'por debajo del PVP recomendado', 'contract_above_list' => 'contrato más caro que la lista', 'no_sales' => 'sin datos de factura', 'no_list' => 'no está en la lista de precios',
    ],
    'flash' => [
        'created' => 'Ejecución iniciada. El informe aparecerá aquí en cuanto se haya leído Lexoffice.', 'deleted' => 'Ejecución eliminada.', 'not_done' => 'La ejecución aún no ha terminado.',
    ],
    'validation' => [
        'need_file' => 'Se necesita al menos un archivo de exportación (Telekom o Quality Hosting).',
    ],
    'hint' => [
        'run_pending' => 'La ejecución aún no ha terminado. Actualiza la página para ver el informe.', 'run_failed' => 'La ejecución falló.', 'unmapped' => 'Las empresas sin asignación se pueden resolver con un archivo de asignación en la próxima ejecución.', 'extras' => 'Facturado sin suscripción activa, o una edición que la conciliación no reconoce.',
        'succession' => 'La duración Telekom se cortó al inicio del contrato de Quality Hosting; de lo contrario cada migración contaría dos veces.', 'price' => 'Los precios de venta proceden de las líneas de factura asignadas; el precio de compra de lista y el PVP recomendado, de la lista de precios para la misma duración y el mismo ritmo.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'months' => 'meses',
];
