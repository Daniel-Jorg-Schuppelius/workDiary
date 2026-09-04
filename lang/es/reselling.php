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
        'assign' => 'Asignar',
        'rerun' => 'Recalcular',
        'remove_mapping' => 'Quitar asignación',
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
        'reference_hint' => 'Los periodos iniciados hasta este día cuentan como vencidos. No hay límite hacia atrás: se comprueba todo desde el primer inicio de contrato de las exportaciones.',
        'before' => 'Días antes del inicio del periodo (pago anticipado)',
        'after' => 'Días después del inicio del periodo (facturación tardía)',
        'window_hint' => 'Una factura pertenece a un periodo si su fecha cae dentro de esta ventana alrededor del inicio del periodo. Deja amplia la parte posterior (dos años por defecto): las facturaciones tardías y los bloques plurianuales se reparten en meses-licencia, nada cuenta dos veces.',
        'strict' => 'Comprobación estricta del producto',
        'strict_hint' => 'Contar solo las líneas de factura cuyo texto nombra la edición. Sin marcar, cuenta cualquier línea Microsoft del contacto en la ventana si no se encuentra una edición coincidente (facturas agrupadas).',
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
        'billed_via' => 'Facturado a través de un socio (cliente externo)',
        'stored_mapping' => 'Asignación guardada',
        'used' => 'Utilizado', 'recognized' => 'Reconocido como',
        'article_price' => 'Precio de artículo (año)',
        'valid_from' => 'Lista de precios válida desde',
    ],
    'status' => [
        'queued' => 'En cola',
        'running' => 'En ejecución',
        'done' => 'Terminada',
        'failed' => 'Fallida',
    ],
    'section' => [
        'lines' => 'Líneas de factura encontradas para los contactos asignados',
        'summary' => 'Resumen', 'price' => 'Comprobación de precios', 'findings' => 'Periodos', 'mappings' => 'Asignación empresa marketplace → contacto Lexoffice',
        'extras' => 'Líneas Microsoft sin periodo vencido', 'successions' => 'Sucesiones Telekom → Quality Hosting', 'issues' => 'Notas de los archivos', 'errors' => 'Errores de lectura', 'files' => 'Archivos y opciones',
    ],
    'filter' => [
        'status' => 'Estado', 'problems' => 'Solo señalados', 'all' => 'Todos', 'company' => 'Empresa', 'all_companies' => 'Todas las empresas',
    ],
    'empty' => [
        'lines' => 'No se encontraron líneas de factura.',
        'runs' => 'Aún no hay ejecuciones. Sube los archivos de exportación para iniciar la primera conciliación.', 'findings' => 'No hay periodos en esta selección.', 'price' => 'No hay contratos activos o no se subió una lista de precios.', 'mappings' => 'No hay empresas.', 'extras' => 'No hay líneas adicionales.', 'successions' => 'No se detectaron sucesiones.',
    ],
    'price_flag' => [
        'below_list' => 'por debajo del coste', 'below_uvp' => 'por debajo del PVP recomendado', 'contract_above_list' => 'contrato más caro que la lista', 'no_sales' => 'sin datos de factura', 'no_list' => 'no está en la lista de precios',
    ],
    'flash' => [
        'mapping_saved' => 'Asignación guardada. Con «Recalcular» se aplica al informe.', 'mapping_removed' => 'Asignación eliminada.', 'rerun' => 'La ejecución se está recalculando.',
        'created' => 'Ejecución iniciada. El informe aparecerá aquí en cuanto se haya leído Lexoffice.', 'deleted' => 'Ejecución eliminada.', 'not_done' => 'La ejecución aún no ha terminado.',
    ],
    'validation' => [
        'customer_required' => 'Selecciona un cliente.', 'contact_required' => 'Indica un UUID de contacto Lexoffice.',
        'need_file' => 'Se necesita al menos un archivo de exportación (Telekom o Quality Hosting).',
    ],
    'hint' => [
        'lines' => 'Diagnóstico: todo lo que la conciliación vio en Lexoffice para los contactos asignados en el periodo, con la cantidad utilizada. Una empresa sin filas aquí no tiene facturas para su contacto en el periodo.',
        'lines_hidden' => ':count posiciones sin relación con licencias (servicios propios, hardware, dominios) están ocultas.',
        'run_pending' => 'La ejecución aún no ha terminado. Actualiza la página para ver el informe.', 'run_failed' => 'La ejecución falló.', 'unmapped' => 'Las empresas sin asignación se pueden resolver con un archivo de asignación en la próxima ejecución.', 'extras' => 'Facturado sin suscripción activa, o una edición que la conciliación no reconoce.',
        'mapping' => 'Con «Asignar» defines por empresa quién recibe la factura: la propia empresa, un socio o un contacto Lexoffice. Las asignaciones guardadas tienen prioridad sobre la detección automática.',
        'foreign' => 'Los clientes finales de un socio (clientes externos) se comprueban a través del socio: la factura va al socio, que la traslada. Crea los clientes externos bajo el cliente socio, o añade «Empresa;partner:<nombre o Sqid>» al archivo de asignación.',
        'succession' => 'La duración Telekom se cortó al inicio del contrato de Quality Hosting; de lo contrario cada migración contaría dos veces.', 'price' => 'Los precios de venta proceden de las líneas de factura asignadas; el precio de compra de lista y el PVP recomendado, de la lista de precios para la misma duración y el mismo ritmo. El precio de artículo es tu precio de venta actual del maestro de artículos de Lexoffice, escalado a la duración; sin datos de factura sirve como referencia.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'mapping' => [
        'title' => 'Asignar empresa',
        'submit' => 'Guardar asignación',
        'hint' => 'La asignación se aplica a todas las ejecuciones futuras de esta organización. Después usa «Recalcular» para que aparezca en el informe.',
        'mode_label' => 'Facturación',
        'mode' => [
            'customer' => 'Directamente: la empresa es el cliente',
            'partner' => 'A través de un socio (cliente externo)',
            'contact' => 'Contacto Lexoffice',
        ],
        'mode_hint' => [
            'customer' => 'La factura va a este cliente mismo.',
            'partner' => 'El cliente elegido recibe la factura y la traslada. La empresa se crea como cliente externo bajo él si falta.',
            'contact' => 'Sin datos maestros: se comprueban las facturas de este contacto Lexoffice.',
        ],
        'customer' => 'Cliente o socio',
        'customer_placeholder' => 'Elegir cliente',
        'contact' => 'UUID del contacto Lexoffice',
        'contact_hint' => 'Solo necesario para «Contacto Lexoffice»; está en la URL de Lexoffice del contacto.',
    ],
    'line' => [
        'header_only' => 'Documento sin líneas',
        'microsoft' => 'Línea Microsoft',
        'other' => 'Otro',
    ],
    'months' => 'meses',
];
