<?php

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
];
