<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : invoicing.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'service' => 'Service',
    'service_on' => 'Service on :date',
    'hourly_rate' => 'Hourly rate',
    'unit_hour' => 'h',
    'unit_flat' => 'flat',
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
        'gaeb' => [
            'button' => 'GAEB (X89)',
            'button_title' => 'Download the invoice as a GAEB file for construction clients',
        ],
        'zugferd' => [
            'button' => 'ZUGFeRD (PDF)',
            'button_title' => 'Download ZUGFeRD PDF (PDF/A-3, EN 16931)',
            'error_intro' => 'The ZUGFeRD PDF cannot be generated:',
            'unavailable' => 'ZUGFeRD PDF generation is not available on this system (php-pdf-toolkit missing).',
            'failed' => 'ZUGFeRD PDF generation failed.',
        ],
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

    // Invoice preview in the create dialog (MVP-462).
    'source_times' => 'Show :count source time entry|Show :count source time entries',
    'preview' => [
        'heading' => 'Preview:',
        'empty' => 'No billable times or travel charges match the selected filters.',
        'entry_count' => ':count entry|:count entries',
        'travel' => '+ :count travel charge(s)',
        'warning_late' => ':count late entry: service date falls into an already billed period.|:count late entries: service dates fall into already billed periods.',
        'column' => [
            'description' => 'Item',
            'duration' => 'Duration',
            'rate' => 'Rate',
            'amount' => 'Amount',
        ],
        'entries_heading' => 'Show/exclude individual time entries',
        'exclude' => 'exclude',
        'exclude_hint' => 'Excluded entries stay open and reappear in the next invoicing run.',
    ],
    // Girocode/EPC-QR auf dem Rechnungs-PDF (Feature 111, MVP-600).
    'girocode' => [
        'alt' => 'Payment QR code',
        'hint' => 'Scan with your banking app',
    ],
    // Sicherheitseinbehalte § 17 VOB/B (Feature 113, MVP-602).
    'retention' => [
        'dialog_title' => 'Record a retention',
        'submit' => 'Record',
        'dialog_hint' => 'The retention appears on the document and is deducted from the open item. It cannot be changed once the invoice is issued.',
        'kind' => 'Type',
        'basis' => 'Basis',
        'basis_percent' => 'Percentage of the invoice total',
        'basis_amount' => 'Fixed amount',
        'base_kind' => 'Calculation basis',
        'percent' => 'Percentage',
        'amount' => 'Fixed amount',
        'due_on' => 'Payable from',
        'due_on_hint' => 'From this day the retention is a normal open item and is dunned again.',
        'note' => 'Note',
        'heading' => 'Retentions',
        'action' => 'Record retention',
        'release' => 'Release',
        'column_kind' => 'Type',
        'column_amount' => 'Amount',
        'column_due' => 'Payable from',
        'column_status' => 'Status',
        'payable' => 'Amount payable',
        'locked' => 'Retentions can only be changed on a draft invoice — they appear on the document and become part of the frozen state once it is issued.',
        'needs_one_basis' => 'Please provide either a percentage OR a fixed amount.',
        'no_total' => 'The document has no total yet for a retention to relate to.',
        'amount_positive' => 'The retention must be greater than zero.',
        'exceeds_total' => 'The retentions exceed the invoice total.',
        'not_open' => 'This retention is no longer open.',
        'pdf_line' => 'less :basis :kind pursuant to § 17 VOB/B',
        'pdf_due' => 'payable from :date',
        'pdf_payable' => 'Amount payable',
        'dunning_note' => 'less retention',
        'added' => 'Retention recorded.',
        'released' => 'Retention released.',
    ],
];
