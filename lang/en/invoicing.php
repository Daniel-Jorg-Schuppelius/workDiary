<?php

return [
    'service' => 'Service',
    'service_on' => 'Service on :date',
    'hourly_rate' => 'Hourly rate',
    'unit_hour' => 'h',
    'unit_piece' => 'pc',
    'tax_rate' => 'Tax rate',
    'currency' => 'Currency',
    'totals' => [
        'net' => 'Net',
        'tax' => 'Tax',
        'gross' => 'Gross',
    ],

    // E-invoicing (feature 045, section 8): XRechnung (UBL 2.1, EN 16931).
    'buyer_reference' => 'Routing ID / buyer reference (BT-10)',
    'buyer_reference_hint' => 'Required for XRechnung (e-invoice): the Leitweg-ID for public authorities, otherwise a reference provided by the customer.',
    'einvoice' => [
        'button' => 'XRechnung',
        'button_title' => 'Download XRechnung (UBL 2.1, EN 16931)',
        'error_intro' => 'The XRechnung cannot be generated:',
        'payment_terms' => 'Payable within :days days without deduction.',
        'exemption_small_business' => 'No VAT charged according to § 19 UStG (German small business scheme).',
        'error' => [
            'status' => 'The invoice must be issued or paid.',
            'no_items' => 'The invoice has no line items.',
            'missing_buyer_reference' => 'The customer is missing the routing ID/buyer reference (BT-10).',
            'missing_seller_field' => 'Seller detail missing: :field (organisation settings → invoicing).',
            'missing_tax_id' => 'Neither VAT ID nor tax number is configured in the organisation settings.',
            'missing_iban' => 'IBAN for the SEPA credit transfer is missing in the organisation settings.',
            'missing_tax_rate' => 'The invoice has no tax rate.',
            'totals_mismatch' => 'The invoice totals are inconsistent (lines, subtotal, tax, total).',
        ],
        'warning' => [
            'missing_seller_contact' => 'Seller contact incomplete (name, phone, email) — XRechnung requires full contact details (BR-DE-2).',
            'missing_bic' => 'BIC is missing (recommended for SEPA credit transfers).',
            'buyer_address_incomplete' => 'Customer address incomplete (street/ZIP/city).',
            'missing_buyer_email' => 'Customer email is missing (electronic delivery address BT-49).',
            'missing_due_date' => 'Due date missing — the default payment term is used.',
        ],
    ],
];
