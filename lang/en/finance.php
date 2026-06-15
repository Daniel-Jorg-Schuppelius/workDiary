<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'module' => 'Finance interface',
        'transfers' => 'Transfer receipts',
        'transfer' => 'Transfer receipt',
        'menu' => 'Invoicing handover',
        'positions' => 'Resulting positions',
        'sources' => 'Individual sources (snapshot)',
        'events' => 'Event log',
    ],

    'subtitle' => [
        'transfers' => 'Hand over billable time and materials to the leading invoicing system in separate channels.',
    ],

    'field' => [
        'billing_mode' => 'Billing channel',
        'billing_mode_inherit' => '— Inherit organisation default —',
        'billing_mode_default' => '— WorkDiary (default) —',
        'billing_mode_hint' => 'Overrides the organisation default for this customer. With Lexoffice/DATEV, local invoicing is locked.',
        'billing_mode_org_hint' => 'Default billing channel of the organisation. Customers can override it individually.',
        'channel' => 'Transfer channel',
        'target' => 'Transfer target',
        'status' => 'Status',
        'period' => 'Service period',
        'position_count' => 'Positions',
        'total_amount' => 'Total amount (net)',
        'total_quantity' => 'Total quantity',
        'payload_hash' => 'Payload hash',
        'transferred_at' => 'Transferred at',
        'failure_reason' => 'Failure reason',
        'customer' => 'Customer',
        'source' => 'Source',
        'source_deleted' => 'Source no longer available',
    ],

    'action' => [
        'create_draft' => 'Prepare transfer',
        'confirm' => 'Confirm transfer',
        'mark_transferred' => 'Mark as transferred',
        'mark_failed' => 'Mark as failed',
        'void' => 'Void transfer',
        'show' => 'Show',
        'execute' => 'Transfer now',
        'retry' => 'Retry',
        'download' => 'Download handover package',
        'open_external' => 'Open externally',
    ],

    'filter' => [
        'all' => 'All',
    ],

    'hint' => [
        'channels_separate' => 'Time and material are confirmed as separate handover packages.',
        'datev_desktop_api' => 'DATEV leads: handover as a file package (CSV) — the DATEV desktop API will follow as a separate adapter.',
        'target_by_mode' => 'The target is preset from the customer\'s billing channel.',
        'period_sources' => 'Only billable sources that have not yet been invoiced/handed over within the period are collected.',
        'lexoffice_draft_created' => 'Invoice draft created in Lexoffice:',
    ],

    'confirm_execute' => 'Transfer to the target now? On success the sources will be marked as handed over.',
    'confirm_void' => 'Void this transfer? The sources will be released again.',

    'empty_title' => 'No transfer receipts',
    'empty_message' => 'No transfers have been prepared yet.',
    'empty_filtered' => 'No transfers match the selected filters.',
    'empty_positions_title' => 'No positions',
    'empty_positions' => 'The sources do not produce any positions (e.g. sources deleted).',

    'csv' => [
        'package_title' => 'WorkDiary handover package (CSV) — not a DATEV format',
        'position' => 'Position',
        'date' => 'Date',
        'employee' => 'Employee',
        'project' => 'Project/Order',
        'activity' => 'Activity',
        'hours' => 'Hours',
        'rate' => 'Rate',
        'amount' => 'Amount',
        'comment' => 'Comment',
        'product' => 'Product',
        'quantity' => 'Quantity',
        'unit' => 'Unit',
        'unit_price_net' => 'Unit price (net)',
        'tax_rate' => 'Tax rate',
        'cost_position' => 'Cost position',
        'total' => 'Total',
    ],

    'lexoffice' => [
        'introduction' => 'Handover from WorkDiary — :channel, period :from – :to.',
    ],

    'flash' => [
        'created' => 'Transfer receipt draft created.',
        'confirmed' => 'Transfer confirmed.',
        'transferred' => 'Transfer completed — sources have been marked as transferred.',
        'failed' => 'Transfer marked as failed.',
        'voided' => 'Transfer voided — sources have been released again.',
    ],

    'error' => [
        'local_invoicing_locked' => 'Invoicing is led by :program; local invoice creation is locked.',
        'no_sources' => 'No transferable sources found in the selected period.',
        'illegal_transition' => 'Status transition from ":from" to ":to" is not allowed.',
        'void_after_transfer' => 'A transfer that has already been delivered cannot be voided — please use a cancellation/difference transfer.',
        'entry_already_transferred' => 'The time entry has already been handed over to invoicing and can no longer be corrected.',
        'target_not_allowed' => 'This target is not allowed for the billing channel ":mode".',
        'lexoffice_not_configured' => 'Lexoffice is not configured for this organisation (API key missing).',
        'sources_missing' => 'The sources of this transfer receipt are no longer fully available.',
    ],

    'datev' => [
        'title' => 'DATEV booking batch',
        'menu' => 'DATEV booking batch',
        'subtitle' => 'Hand over issued invoices, credit notes and approved expenses of a closed period as an auditable DATEV booking batch (V700).',
        'empty' => 'No booking batches created yet.',
        'empty_sources' => 'No booking records in this batch.',
        'field' => [
            'batch_no' => 'Batch no.',
            'period' => 'Period',
            'status' => 'Status',
            'booking_count' => 'Booking records',
            'total' => 'Total',
            'hash' => 'File hash (SHA-256)',
            'open_ready' => 'Open documents ready for booking',
            'document_ref' => 'Document field 1',
            'soll_haben' => 'D/C',
            'account' => 'Account',
            'contra_account' => 'Contra account',
            'tax_key' => 'Tax key (BU)',
            'amount' => 'Amount (gross)',
            'lock_flag' => 'Lock',
            'include_expenses' => 'Include approved expenses',
            'debtor_no' => 'Debtor number (DATEV)',
            'debtor_no_hint' => 'Leave empty to derive the number automatically from the configured number range.',
        ],
        'lock' => [
            'on' => 'locked',
            'off' => 'not locked',
        ],
        'action' => [
            'create' => 'Create batch',
            'finalize' => 'Finalize',
            'download' => 'Download CSV',
            'configure' => 'Configuration',
            'save_config' => 'Save configuration',
        ],
        'dialog' => [
            'create_title' => 'Create DATEV booking batch',
            'create_hint' => 'Documents of the period that are ready for booking are compiled. Externally managed invoices are excluded.',
        ],
        'hint' => [
            'period_sources' => 'Issued/paid invoices with a document date within the period that are not yet part of a finalized batch are taken into account.',
            'include_expenses' => 'Optional: additionally include approved expenses as an expense booking (MVP — simplified accounts).',
        ],
        'flash' => [
            'created' => 'Booking batch created as a draft.',
            'finalized' => 'Booking batch finalized — CSV generated and sources marked as handed over.',
            'config_saved' => 'Accounting configuration saved.',
        ],
        'error' => [
            'no_sources' => 'No documents ready for booking were found in the selected period.',
            'already_finalized' => 'The booking batch has already been finalized and is immutable.',
            'storage_failed' => 'The DATEV file could not be saved.',
            'unavailable' => 'The DATEV export is not available in this installation (format package missing).',
            'preflight_failed' => 'The batch cannot be finalized due to preflight errors.',
            'no_organization' => 'No organisation could be resolved.',
            'roundtrip_failed' => 'The generated DATEV file failed the re-import check: :errors',
        ],
        'preflight' => [
            'no_sources' => 'The batch contains no booking records.',
            'missing_client_numbers' => 'Advisor and client number must be set in the configuration.',
            'missing_debtor' => 'Document :ref has no valid debtor account.',
            'missing_revenue' => 'Document :ref has no revenue account.',
            'unknown_tax_key' => 'Document :ref: no tax key (BU) set for tax rate :rate %.',
            'external_excluded' => ':count externally managed invoice(s) were excluded from the local booking batch.',
        ],
        'roundtrip' => [
            'unsupported' => 'The file was not recognised as a supported DATEV format.',
            'version_mismatch' => 'Unexpected DATEV format version (:version instead of 700).',
            'parse_failed' => 'The generated file could not be re-read: :message',
            'row_count_mismatch' => 'Re-read booking rows (:actual) differ from the expected count (:expected).',
        ],
        'format' => [
            'label' => 'Format',
            'value' => 'DATEV booking batch (EXTF V700)',
            'encoding' => 'Character set',
            'verified' => 'Re-import check passed',
        ],
        'loss' => [
            'title' => 'Derived and simplified fields',
            'hint' => 'These fields are derived or simplified for the DATEV export and should be reviewed before transfer.',
            'booking_date' => 'Document date = start of period (derived from the batch period, not per document).',
            'expense_account' => 'Expenses are booked to the revenue/expense account in a simplified way (no differentiated expense/input-tax accounts per category).',
            'missing_tax_key' => 'Documents without a tax key (BU) are transferred without tax split.',
        ],
        'config' => [
            'title' => 'DATEV accounting configuration',
            'subtitle' => 'Advisor/client number, chart of accounts, ledger accounts, debtor number range and tax keys per organisation.',
            'client_group' => 'Advisor & client',
            'advisor_number' => 'Advisor number',
            'client_number' => 'Client number',
            'accounts_group' => 'Accounts',
            'skr' => 'Chart of accounts',
            'account_length' => 'Ledger account length',
            'revenue_account' => 'Revenue account (default)',
            'revenue_account_tax_free' => 'Revenue account (tax-free / 0 %)',
            'debtor_base' => 'Debtor number range base',
            'debtor_base_hint' => 'If an explicit debtor number is missing on the customer, it is formed from base + customer ID.',
            'tax_group' => 'Tax keys (DATEV BU)',
            'tax_key_19' => 'Tax key (BU) 19 %',
            'tax_key_7' => 'Tax key (BU) 7 %',
            'tax_key_0' => 'Tax key (BU) 0 % / tax-free',
            'export_group' => 'Export',
            'finalize' => 'Set lock flag (GoBD)',
            'finalize_hint' => 'Marks the bookings as locked on export.',
            'encoding' => 'Character set',
            'encoding_hint' => 'ISO-8859-1 is customary for DATEV; UTF-8 only if explicitly desired.',
        ],
    ],
];
