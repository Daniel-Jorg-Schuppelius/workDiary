<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'service' => 'Servicio',
    'service_on' => 'Servicio el :date',
    'hourly_rate' => 'Tarifa horaria',
    'unit_hour' => 'h',
    'unit_flat' => 'global',
    'unit_piece' => 'ud',
    'tax_rate' => 'Tipo impositivo',
    'currency' => 'Moneda',
    'totals' => [
        'net' => 'Neto',
        'tax' => 'Impuesto',
        'gross' => 'Bruto',
    ],

    // Facturación electrónica (funcionalidad 045, sección 8): XRechnung (UBL 2.1, EN 16931).
    'buyer_reference' => 'Leitweg-ID / referencia del comprador (BT-10)',
    'buyer_reference_hint' => 'Obligatorio para la XRechnung (factura electrónica): el Leitweg-ID para administraciones públicas, en otro caso una referencia facilitada por el cliente.',
    'einvoice' => [
        'button' => 'XRechnung',
        'button_title' => 'Descargar la XRechnung (UBL 2.1, EN 16931)',
        'error_intro' => 'No se puede generar la XRechnung:',
        'gaeb' => [
            'button' => 'GAEB (X89)',
            'button_title' => 'Descargar la factura como archivo GAEB para clientes de construcción',
        ],
        'zugferd' => [
            'button' => 'ZUGFeRD (PDF)',
            'button_title' => 'Descargar el PDF ZUGFeRD (PDF/A-3, EN 16931)',
            'error_intro' => 'No se puede generar el PDF ZUGFeRD:',
            'unavailable' => 'La generación del PDF ZUGFeRD no está disponible en este sistema (falta php-pdf-toolkit).',
            'failed' => 'La generación del PDF ZUGFeRD ha fallado.',
        ],
        'payment_terms' => 'Pagadero en :days días sin descuento.',
        'exemption_small_business' => 'Sin IVA conforme al § 19 UStG (régimen alemán de pequeñas empresas).',
        'error' => [
            'status' => 'La factura debe estar emitida o pagada.',
            'no_items' => 'La factura no contiene posiciones.',
            'missing_buyer_reference' => 'Al cliente le falta el Leitweg-ID/referencia del comprador (BT-10).',
            'missing_seller_field' => 'Falta un dato del vendedor: :field (configuración de la organización → facturación).',
            'missing_tax_id' => 'Ni NIF-IVA ni número fiscal configurados en la configuración de la organización.',
            'missing_iban' => 'Falta el IBAN para la transferencia SEPA en la configuración de la organización.',
            'missing_tax_rate' => 'La factura no tiene tipo impositivo.',
            'totals_mismatch' => 'Los totales de la factura son incoherentes (posiciones, subtotal, impuesto, total).',
        ],
        'warning' => [
            'missing_seller_contact' => 'Contacto del vendedor incompleto (nombre, teléfono, correo) — la XRechnung exige datos de contacto completos (BR-DE-2).',
            'missing_bic' => 'Falta el BIC (recomendado para transferencias SEPA).',
            'buyer_address_incomplete' => 'Dirección del cliente incompleta (calle/CP/ciudad).',
            'missing_buyer_email' => 'Falta el correo del cliente (dirección electrónica de recepción BT-49).',
            'missing_due_date' => 'Falta la fecha de vencimiento — se usa el plazo de pago predeterminado.',
        ],
    ],

    // Vista previa de la factura en el diálogo de creación (MVP-462).
    'source_times' => 'Mostrar :count registro de tiempo de origen|Mostrar :count registros de tiempo de origen',
    'preview' => [
        'heading' => 'Vista previa:',
        'empty' => 'No hay tiempos facturables ni desplazamientos para los filtros seleccionados.',
        'entry_count' => ':count registro|:count registros',
        'travel' => '+ :count desplazamiento(s)',
        'warning_late' => ':count registro tardío: la fecha de prestación cae en un período ya facturado.|:count registros tardíos: las fechas de prestación caen en períodos ya facturados.',
        'column' => [
            'description' => 'Posición',
            'duration' => 'Duración',
            'rate' => 'Tarifa',
            'amount' => 'Importe',
        ],
        'entries_heading' => 'Mostrar/excluir registros individuales',
        'exclude' => 'excluir',
        'exclude_hint' => 'Los registros excluidos permanecen abiertos y reaparecen en el próximo ciclo de facturación.',
    ],
    // Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
    'girocode' => [
        'alt' => 'Código QR de pago',
        'hint' => 'Escanear con la app bancaria',
    ],
    // Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
    'retention' => [
        'dialog_title' => 'Registrar una retención',
        'submit' => 'Registrar',
        'dialog_hint' => 'La retención figura en el documento y se descuenta de la partida abierta. Tras la emisión ya no se puede modificar.',
        'kind' => 'Tipo',
        'basis' => 'Base',
        'basis_percent' => 'Porcentaje del total de la factura',
        'basis_amount' => 'Importe fijo',
        'base_kind' => 'Base de cálculo',
        'percent' => 'Porcentaje',
        'amount' => 'Importe fijo',
        'due_on' => 'Pagadero a partir del',
        'due_on_hint' => 'A partir de ese día la retención es una partida abierta normal y se vuelve a reclamar.',
        'note' => 'Nota',
        'heading' => 'Retenciones de garantía',
        'action' => 'Registrar retención',
        'release' => 'Liberar',
        'column_kind' => 'Tipo',
        'column_amount' => 'Importe',
        'column_due' => 'Pagadero a partir del',
        'column_status' => 'Estado',
        'payable' => 'Importe a pagar',
        'locked' => 'Las retenciones de garantía solo se pueden modificar en el borrador de la factura — figuran en el documento y, tras su emisión, forman parte del estado congelado.',
        'needs_one_basis' => 'Indique un porcentaje O un importe fijo.',
        'no_total' => 'El documento aún no tiene un total al que referir una retención.',
        'amount_positive' => 'La retención debe ser mayor que cero.',
        'exceeds_total' => 'Las retenciones superan el total de la factura.',
        'not_open' => 'Esta retención ya no está abierta.',
        'pdf_line' => 'menos :basis :kind según el § 17 VOB/B',
        'pdf_due' => 'pagadero a partir del :date',
        'pdf_payable' => 'Importe a pagar',
        'dunning_note' => 'menos la retención de garantía',
        'added' => 'Retención registrada.',
        'released' => 'Retención liberada.',
    ],

    // Leistungszeitraum je Position (Feature 152, Review 2026-09-11).
    // Freie Rechnungen aus Artikeln, Material und Fertigung (Feature 160, MVP-856–859).
    'free' => [
        'title' => [
            'create' => 'Crear factura',
            'deliveries' => 'Incorporar entregas',
        ],
        'option' => [
            'manual' => 'Componer las posiciones manualmente (artículos, material, fabricación)',
            'no_variant' => '— sin variante —',
        ],
        'field' => [
            'variant' => 'Variante',
            'delivery' => 'Entrega',
            'order' => 'Orden de fabricación',
            'delivered_on' => 'Entregado el',
        ],
        'action' => [
            'attach_deliveries' => 'Incorporar entrega',
            'open_order' => 'Abrir orden de fabricación',
        ],
        'hint' => [
            'manual' => 'Sin periodo ni tiempos: el borrador empieza vacío; las posiciones vienen de artículos, material, texto libre o entregas de fabricación.',
            'empty_draft' => 'Añada posiciones o incorpore una entrega. Un borrador vacío no se puede emitir ni enviar.',
            'variant' => 'Opcional; la variante rellena precio y número de artículo.',
            'no_stock_movement' => 'Las posiciones de artículo y texto libre no mueven existencias; una entrega se registra en almacén/entrega.',
            'price_required' => 'Introdúzcalo conscientemente; 0,00 para una posición gratuita es válido.',
            'currency_mismatch' => 'El precio del artículo está en otra moneda que el documento; introduzca el precio en la moneda del documento.',
            'deliveries' => 'Entregas realizadas y no facturadas del cliente en el periodo de cabecera :from – :to; cada entrega se incorpora completa como una posición.',
            'deliveries_empty' => 'Los productos sin entrega se venden como posición de artículo libre.',
            'deliveries_rules' => 'La cantidad está ligada a la fuente, el precio de venta viene de la entrega y sigue editable en el borrador. Quitar la posición o descartar el borrador libera la entrega; las existencias no cambian.',
        ],
        'label' => [
            'from_order' => 'orden de fabricación :number',
            'source_delivery' => 'Entrega del :date · :order · cantidad entregada :quantity',
            'reserved' => 'reservada en borrador',
        ],
        'empty' => [
            'deliveries' => 'Sin entregas abiertas.',
        ],
        'flash' => [
            'draft_created' => 'Borrador de factura creado; añada ahora las posiciones.',
            'deliveries_attached' => ':count entrega(s) incorporada(s).',
        ],
        'error' => [
            'empty' => 'El borrador no tiene posiciones y no se puede emitir ni enviar.',
            'variant_mismatch' => 'La variante no pertenece al artículo elegido.',
            'draft_only' => 'Las entregas solo se incorporan a un borrador de factura normal.',
            'delivery_required' => 'Elija al menos una entrega.',
            'delivery_foreign' => 'La entrega no pertenece a esta organización.',
            'delivery_customer' => 'La entrega pertenece a otro cliente.',
            'delivery_external' => 'La entrega se factura externamente.',
            'delivery_not_delivered' => 'La entrega aún no se ha realizado.',
            'delivery_invoiced' => 'La entrega ya está facturada.',
            'delivery_reserved' => 'La entrega «:name» ya está reservada en el borrador :number.',
            'delivery_currency' => 'La entrega está en :currency y el documento en :invoice; sin conversión automática.',
            'delivery_project' => 'La entrega pertenece a otro proyecto.',
            'delivery_without_price' => 'La entrega «:name» no tiene precio de venta; manténgalo en el artículo/la variante o registre una posición libre.',
        ],
    ],

    'item' => [
        'service_period' => 'Periodo de prestación',
        'service_from' => 'Periodo de prestación desde',
        'service_to' => 'Periodo de prestación hasta',
    ],
];
