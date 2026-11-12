<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Patrones llamativos (defectos en serie, lotes)',
        'hint' => 'Grupos con al menos :threshold reclamaciones en el periodo. Una indicación, no una decisión.',
        'none' => 'No hay patrones llamativos en el periodo.',
        'rule_label' => 'Regla',
        'group' => 'Grupo',
        'cases' => 'Casos',
        'count' => 'Cantidad',
        'rule' => [
            'lot' => 'Lote',
            'article_defect' => 'Artículo × tipo de defecto',
            'article_cause' => 'Artículo × causa',
            'supplier_defect' => 'Proveedor × tipo de defecto',
            'entry_type_cause' => 'Tipo de orden × causa',
        ],
        'notify_title' => 'Patrón de reclamaciones llamativo: :label',
        'notify_message' => ':count reclamaciones en :days días (:rule).',
    ],
    // Lagerbezug der Rückläufer (MVP-1576).
    'rma' => [
        'group_return' => 'Devolución',
        'group_receipt' => 'Entrada de mercancía',
        'group_stock' => 'Referencia de almacén',
        'hint_stock' => 'Con almacén, variante y cantidad, la entrada de mercancía contabiliza las existencias en cuarentena. Sin variante, la devolución solo se documenta.',
        'hint_warehouse' => 'Necesario para el asiento de existencias en cuanto se elige una variante.',
        'hint_serial' => 'La selección fija la variante y la cantidad 1.',
        'option' => ['no_stock' => '— sin artículo de almacén —'],
        'without_stock' => 'sin referencia de almacén — sin asiento de existencias',
        'no_stock_module' => 'El módulo de almacén no está activo. La devolución se registra sin asiento de existencias.',
        'disposition_books' => 'La decisión traspasa las existencias fuera de la cuarentena o las da de baja.',
        'disposition_documents' => 'Esta devolución no tiene referencia de almacén. La decisión se registra sin asiento de existencias.',
        'flash' => [
            'received_booked' => 'Entrada de mercancía contabilizada en cuarentena (:state).',
            'received_unbooked' => 'Entrada de mercancía registrada sin asiento de existencias (sin artículo de almacén o módulo de almacén inactivo).',
            'disposition_booked' => 'Decisión de uso contabilizada.',
            'disposition_unbooked' => 'Decisión de uso registrada sin asiento de existencias.',
        ],
        'error' => [
            'serial_variant' => 'El número de serie pertenece a otra variante de artículo.',
            'serial_qty' => 'Una devolución con número de serie tiene la cantidad 1.',
            'lot_variant' => 'El lote pertenece a otra variante de artículo.',
        ],
    ],
    // Bezugsobjekte, Einstieg, Finanzfolge und Regress der Akte (MVP-1577).
    'form' => [
        'title_edit' => 'Editar reclamación',
        'lot_article_mismatch' => 'El lote pertenece a otro artículo.',
        'group_links' => 'Objetos afectados',
        'hint_links' => 'Vincule aquello a lo que se refiere la reclamación. Con un cliente seleccionado, las listas muestran solo sus objetos.',
        'hint_serial' => 'Un número de serie conocido en el almacén se vincula al expediente.',
        'serial_linked' => 'El número de serie es conocido en el almacén y está vinculado.',
    ],
    'open' => [
        'action' => 'Abrir reclamación',
    ],
    'financial' => [
        'hint_invoice_local' => 'El abono, la anulación, la reducción de precio y la corrección se crean a partir de esta factura. La anulación requiere una factura emitida e impagada; el abono, una factura pagada.',
        'hint_invoice_external' => 'Las facturas de este cliente se gestionan en un sistema externo. El documento rectificativo se crea allí; anote después aquí su número.',
        'source_invoice' => 'de la factura :number',
        'error' => [
            'invoice_required' => 'Seleccione la factura a partir de la cual debe crearse el documento rectificativo.',
            'not_cancellable' => 'La factura :number no se puede anular. La anulación requiere una factura emitida e impagada.',
            'not_creditable' => 'No se puede crear un abono para la factura :number. Debe estar pagada y no tener todavía ninguna corrección.',
            'not_rejectable' => 'Solo se pueden rechazar las consecuencias propuestas.',
        ],
        'reject' => 'Rechazar',
        'reject_confirm' => '¿Rechazar la propuesta? Termina sin documento; sigue siendo posible una nueva propuesta.',
        'flash' => [
            'rejected' => 'Propuesta rechazada.',
        ],
    ],
    'recourse' => [
        'group_claim' => 'Reclamación',
        'group_reference' => 'Referencia',
        'hint_due_days' => 'Sin indicación se aplican :days días desde la presentación.',
        'hint_terms' => 'Por ejemplo, duración de la garantía, exclusiones o plazo de reclamación según el contrato de suministro.',
        'field_incoming_invoice' => 'Factura recibida',
        'due_days' => 'Plazo de respuesta: :days días desde la presentación',
    ],
    // Retourenlabel einer RMA (MVP-917).
    'return_label' => [
        'title' => 'Etiqueta de devolución',
        'create' => 'Crear etiqueta de devolución',
        'download' => 'Descargar etiqueta',
        'created' => 'Etiqueta de devolución creada (envío :tracking).',
        'no_address' => 'La etiqueta de devolución necesita la dirección del cliente (calle, código postal, ciudad).',
    ],
    // Retourenanmeldung im Kundenportal (MVP-935).
    'portal_return' => [
        'list' => [
            'claim' => 'Reclamación',
            'empty' => 'Todavía no hay devoluciones registradas.',
            'rma' => 'Número de devolución',
            'status' => 'Estado',
            'title' => 'Mis devoluciones',
        ],
        'capability' => 'Registrar una devolución',
        'nav' => 'Registrar devolución',
        'title' => 'Registrar una devolución',
        'intro' => 'Elija la entrega o el objeto, describa el motivo y adjunte fotos si es necesario. Recibirá un número de devolución; si procede, facilitamos una etiqueta de devolución.',
        'empty' => 'No hay entregas ni objetos para su cuenta.',
        'submit' => 'Registrar devolución',
        'label' => 'Descargar etiqueta de devolución',
        'field' => [
            'delivery' => 'Entrega',
            'asset' => 'Objeto',
            'serial_no' => 'Número de serie',
            'quantity' => 'Cantidad',
            'title' => 'Descripción breve',
            'description' => 'Motivo de la devolución',
            'photos' => 'Fotos o justificantes (máx. 5)',
        ],
        'flash' => [
            'submitted' => 'Devolución registrada: reclamación :number, número de devolución :rma.',
        ],
        'error' => [
            'subject' => 'Elija una entrega o un objeto.',
            'serial' => 'Este número de serie no pertenece a la entrega elegida.',
        ],
    ],
    // Nachreichungen aus dem Kundenportal.
    'portal_note' => [
        'history' => 'Sus presentaciones posteriores',
        'notification_title' => 'Presentación posterior para la reclamación :number',
    ],
    'action' => [
        'follow_up_order' => 'Orden de seguimiento',
        'follow_up_none' => '— sin orden de seguimiento —',
    ],
];
