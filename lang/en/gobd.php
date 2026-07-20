<?php

/*
 * GoBD Z3 data submission (Feature 063, MVP-132).
 */

return [
    'title' => 'GoBD data submission (Z3)',
    'subtitle' => 'Tax-relevant data as a GDPdU package for the tax audit (IDEA-readable).',
    'period' => 'Audit period',
    'sections' => 'Data sections',
    'section' => [
        'invoices' => 'Outgoing invoices',
        'invoice_items' => 'Invoice line items',
        'customers' => 'Debtors',
        'time_entries' => 'Time records',
        'booking_batches' => 'Booking batches',
        'booking_batch_items' => 'Booking batch line items',
        'payment_allocations' => 'Payment allocations',
        'cash_entries' => 'Cash book',
        'cash_daily_closings' => 'Daily cash closings',
        'incoming_einvoices' => 'Incoming e-invoices',
        'expenses' => 'Expenses',
    ],
    'preflight' => [
        'title' => 'Preflight',
        'check' => 'Check period',
        'records' => ':count records',
        'warnings' => 'Notices',
        'drafts' => ':count non-finalized invoice(s) (draft) in the period — not yet tax-final.',
        'draft_batches' => ':count non-finalized booking batch(es) (draft) in the period — missing from the booking batch evidence.',
        'empty_invoices' => 'No outgoing invoices in the selected period.',
    ],
    'export' => 'Download Z3 package',
    'recent' => [
        'title' => 'Recent exports',
        'package_hash' => 'Package hash (SHA-256)',
        'records' => 'Records',
        'created' => 'Created',
        'none' => 'No exports yet.',
    ],
    'encoding' => 'Character set of the data files',
];
