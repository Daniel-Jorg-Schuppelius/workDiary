<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gaeb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Mediciones',
    'subtitle' => 'Importar mediciones GAEB y seguir las partidas',
    'empty' => 'Aún no se han importado mediciones.',
    'import_button' => 'Importar archivo GAEB',

    'columns' => [
        'name' => 'Denominación',
        'project' => 'Proyecto',
        'phase' => 'Fase',
        'version' => 'Versión GAEB',
        'items' => 'Partidas',
        'reference_no' => 'Ref.',
        'short_text' => 'Texto corto',
        'quantity' => 'Cantidad',
        'unit' => 'Unidad',
        'unit_price' => 'PU',
        'total_price' => 'Total',
        'type' => 'Tipo',
        'status' => 'Estado',
        'executed' => 'Medición',
        'remaining' => 'Resto',
    ],

    'import' => [
        'title' => 'Importar archivo GAEB',
        'file' => 'Archivo GAEB DA XML',
        'file_hint' => 'GAEB DA XML 3.x (p. ej. .x81, .x83, .x86 o .xml).',
        'project' => 'Proyecto (opcional)',
        'project_none' => '— sin proyecto —',
        'name' => 'Denominación (opcional)',
        'name_hint' => 'Sustituye el nombre del proyecto del archivo.',
        'submit' => 'Importar',
        'status' => [
            'pending' => 'En verificación',
            'preflight_failed' => 'Comprobación previa fallida',
            'imported' => 'Importado',
            'conflict' => 'Conflicto',
        ],
    ],

    'show' => [
        'positions' => 'Partidas',
        'history' => 'Historial de importaciones',
        'no_imports' => 'No hay importaciones registradas.',
        'imported_at' => 'Importado el',
        'back' => 'Volver al listado',
    ],

    'change_order' => [
        'phase' => [
            'CallChangOrder' => 'Solicitud de modificado',
            'SupplBid' => 'Oferta de modificado',
            'SupplAgree' => 'Acuerdo de modificado',
        ],
        'initiator' => [
            'Owner' => 'Promotor',
            'Contractor' => 'Contratista',
        ],
    ],
    'phase' => [
        '31' => 'Medición de cantidades',
        '50' => 'Catálogo de costes de construcción',
        '51' => 'Determinación de costes',
        '52' => 'Datos de cálculo',
        '80' => 'Datos universales del presupuesto',
        '81' => 'Presupuesto descriptivo',
        '82' => 'Estimación de costes',
        '83' => 'Solicitud de oferta',
        '84' => 'Presentación de la oferta',
        '85' => 'Oferta alternativa',
        '86' => 'Adjudicación',
        '87' => 'Confirmación del pedido',
        '89' => 'Factura',
        '89B' => 'Documento justificativo de la factura',
        '83Z' => 'Contrato marco: solicitud de oferta',
        '84Z' => 'Contrato marco: presentación de la oferta',
        '86ZE' => 'Contrato marco: pedido individual',
        '86ZR' => 'Contrato marco: pedido marco',
        '93' => 'Solicitud de precio',
        '94' => 'Oferta de precio',
        '96' => 'Pedido',
        '97' => 'Confirmación del pedido (comercio)',
    ],

    'item' => [
        'type' => [
            'standard' => 'Partida normal',
            'base' => 'Partida base',
            'alternative' => 'Partida alternativa',
            'optional' => 'Partida opcional',
            'lump_sum' => 'Partida a tanto alzado',
            'markup' => 'Partida de recargo',
            'note' => 'Nota',
        ],
        'status' => [
            'draft' => 'Borrador',
            'imported' => 'Importado',
            'quoted' => 'Ofertado',
            'ordered' => 'Adjudicado',
            'in_progress' => 'En curso',
            'completed' => 'Completado',
            'replaced' => 'Sustituido',
            'cancelled' => 'Cancelado',
        ],
        'change_order_status' => [
            'Recog' => 'Reconocido',
            'Filed' => 'Notificado',
            'Offered' => 'Ofertado',
            'Withdrawn' => 'Retirado',
            'Rejected' => 'Rechazado',
            'ObjToRecj' => 'Objeción al rechazo',
            'FormAckn' => 'Reconocido en cuanto al fondo',
            'Approved' => 'Aprobado',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'No se pudo detectar la versión GAEB.',
        'version_unsupported' => 'La versión GAEB :version no es compatible (línea objetivo 3.3).',
        'phase_unknown' => 'La fase de intercambio «:code» es desconocida.',
        'no_items' => 'El archivo no contiene partidas.',
        'vendor_record_type' => 'El archivo contiene :count registros del tipo propietario :type: su contenido no se evalúa (algunos sistemas guardan ahí los grupos de costes).',
        'item_missing_ref' => 'Partida sin número de orden: :text',
        'duplicate_ref' => 'El número de orden :ref aparece varias veces.',
        'missing_quantity' => 'La partida :ref no tiene cantidad.',
        'non_positive_quantity' => 'La partida :ref tiene una cantidad ≤ 0.',
        'missing_unit' => 'La partida :ref no tiene unidad.',
        'missing_price' => 'La partida :ref no tiene precio unitario en una fase con precios.',
        'unpriced_item' => 'La partida :ref no tiene precio ni está marcada como «no ofertada» en la oferta.',
        'priced_but_not_offered' => 'La partida :ref está marcada como «no ofertada» pero lleva un precio unitario.',
        'up_components_mismatch' => 'Partida :ref: la suma de los componentes del precio unitario (:sum) no coincide con el precio unitario (:price).',
        'missing_text' => 'La partida :ref no tiene texto corto/largo.',
        'total_mismatch' => 'El total indicado (:stated) difiere del total recalculado (:computed).',
        'complement_empty' => 'Posición :ref: el complemento de texto del licitador :mark no está cumplimentado.',
        'contractor_missing' => 'Esta fase requiere la dirección del licitador (nombre, calle, código postal y ciudad en los datos maestros de facturación electrónica).',
    ],

    'flash' => [
        'imported' => 'Medición importada con :items partidas.',
        'preflight_failed' => 'Importación cancelada: :count errores de comprobación previa. No se escribió ninguna partida.',
        'conflict' => 'Reimportación cancelada: se sobrescribirían partidas en ejecución (:refs).',
    ],

    'progress' => [
        'from_takeoff' => 'Cantidad recalculada a partir de :lines líneas de medición del X31.',
        'takeoff_skipped' => ':count líneas con una fórmula no admitida se han omitido.',
        'title' => 'Medición / avance',
        'record' => 'Registrar medición',
        'quantity' => 'Cantidad',
        'note' => 'Nota',
        'source' => [
            'manual' => 'Manual',
            'measurement' => 'Medición',
            'protocol' => 'Acta',
            'material' => 'Consumo de material',
        ],
        'flash' => [
            'recorded' => 'Medición registrada.',
        ],
    ],

    'mapping' => [
        'title' => 'Vinculación',
        'add' => 'Vincular',
        'target_type' => 'Tipo de destino',
        'article' => 'Artículo',
        'material' => 'Material',
        'factor' => 'Factor',
        'flash' => [
            'linked' => 'Partida vinculada.',
        ],
    ],

    'workflow' => [
        'status' => 'Establecer estado',
        'add_addendum' => 'Añadir adenda',
        'remaining_title' => 'Trabajo restante',
        'no_remaining' => 'Sin trabajo restante abierto.',
        'flash' => [
            'item_updated' => 'Estado de partida cambiado.',
            'bill_updated' => 'Estado de medición cambiado.',
            'addendum_added' => 'Adenda añadida.',
        ],
    ],

    'costing' => [
        'title' => 'Seguimiento de costes',
        'planned' => 'Previsto',
        'executed' => 'Real (medido)',
        'remaining' => 'Resto',
        'progress' => 'Avance',
    ],

    'export' => [
        'button' => 'Exportar GAEB',
        'title' => 'Exportación GAEB',
        'phase' => 'Fase',
    ],
    'trade' => [
        'missing_own_address' => 'Falta la dirección propia en los datos maestros de facturación electrónica; sin ella el archivo no identifica al comprador.',
        'missing_delivery_date' => 'Sin fecha de entrega el pedido queda indeterminado; se ha usado la fecha del pedido.',
        'missing_supplier_sku' => ':count línea(s) sin referencia de artículo del proveedor — su sistema solo encuentra la mercancía por ella.',
        'missing_supplier_tax_no' => 'El proveedor no tiene ni número fiscal ni NIF-IVA.',
    ],
    'invoice' => [
        'share_net' => 'Importe neto',
        'share_discount' => 'Descuento',
        'share_vat' => 'IVA :rate %',
        'missing_tax_number' => 'Faltan el número fiscal y el NIF-IVA en los datos maestros de facturación electrónica — la ley fiscal exige uno.',
        'missing_recipient' => 'La dirección del destinatario de la factura está incompleta.',
        'missing_service_period' => 'No se pudo deducir un periodo de prestación; se usó la fecha de la factura.',
    ],
    'comparison' => [
        'title' => 'Comparativa de precios',
        'spread' => 'Diferencia',
        'rank' => 'Puesto :rank',
        'gap' => ':percent % por debajo de la siguiente oferta',
        'unusually_low_hint' => 'Una oferta anormalmente baja exige aclaración, no exclusión (§ 16d VOB/A, § 60 VgV).',
        'incomplete_hint' => 'No todos los licitadores ofertaron cada partida — los precios ausentes son huecos, no ceros.',
        'empty_title' => 'Aún no hay ofertas',
        'empty_hint' => 'Importe ofertas (X84) de esta licitación para compararlas.',
        'button' => 'Comparativa de precios',
    ],
];
