<?php

return [
    'action' => 'Convert invoice file to e-invoice', 'title' => 'Import invoice file', 'eyebrow' => 'E-invoice assistant', 'submit' => 'Read invoice',
    'intro' => 'PDF, Word and Excel invoices are read without running macros. Recognised values must be reviewed before issue.',
    'group_source' => 'Source document', 'group_target' => 'Target and output', 'group_invoice' => 'Invoice data', 'group_einvoice' => 'E-invoice',
    'file' => 'Invoice file', 'file_hint' => 'PDF, DOCX, DOC, XLSX or XLS up to 20 MB; OCR is used for PDF scans when available.', 'delivery_format' => 'Preferred output format',
    'review_hint' => 'The original remains unchanged in the DMS. Automatically recognised data is a suggestion, not an approval.',
    'format' => ['pdf' => 'PDF', 'xrechnung' => 'XRechnung (XML)', 'zugferd' => 'ZUGFeRD (hybrid PDF)', 'pdf_xrechnung' => 'PDF and XRechnung (XML)'],
    'default_line' => 'Services according to original invoice :number', 'source_title' => 'Original file for invoice :number', 'source_description' => 'Unchanged source document of the invoice import.',
    'success' => 'Invoice file read and created as a draft. Please review invoice data and line items.', 'options_title' => 'Invoice and e-invoice data', 'options_action' => 'E-invoice data', 'options_saved' => 'Invoice and e-invoice data saved.',
    'invoice_number' => 'Invoice number', 'currency' => 'Currency', 'issue_date' => 'Invoice date', 'due_date' => 'Due date', 'buyer_reference' => 'Buyer reference / routing ID',
    'buyer_reference_hint' => 'Overrides the buyer reference from the customer record for this invoice.', 'buyer_reference_create_hint' => 'Optional per invoice; the customer record is used when empty.',
    'imported_notice' => 'Pre-filled from an invoice file', 'imported_detail' => 'Recognition score: :confidence%. Check number, dates, amounts, tax and line items against the original.', 'original' => 'Original file',
    'preferred_format' => 'Preferred output:', 'flexibility_hint' => 'PDF, XRechnung and ZUGFeRD remain available separately.', 'mail_hint' => 'Select the attachment format. Drafts are automatically issued when sent.',
    'error' => ['external_billing' => 'An external application controls billing for this customer. Local invoice import is locked.', 'duplicate' => 'This invoice file has already been imported.', 'no_text' => 'No invoice data could be read from the file.', 'unsupported_format' => 'PDF, DOCX, DOC, XLSX and XLS are supported.', 'unreadable' => 'The invoice file is damaged or could not be read safely.', 'proforma' => 'Pro forma invoices can only be sent as PDF.'],
    'warning' => ['missing_number' => 'The invoice number was not recognised reliably.', 'missing_issued_on' => 'The invoice date was not recognised reliably.', 'missing_net' => 'The net amount was not recognised reliably.', 'totals_mismatch' => 'The recognised net, tax and gross amounts are inconsistent.', 'duplicate_number' => 'The recognised invoice number already exists; an available local number was used.'],
];
