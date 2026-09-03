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
        'menu' => 'Licence reconciliation',
        'index' => 'Licence reselling reconciliation',
        'show' => 'Reconciliation run',
    ],
    'subtitle' => 'Compare marketplace subscriptions (Telekom, Quality Hosting) with the Lexoffice outgoing invoices: missing, partial and below-cost periods, plus a price check against the reseller price list.',
    'action' => [
        'new' => 'New run',
        'download' => 'CSV',
        'delete' => 'Delete',
        'refresh' => 'Refresh',
        'assign' => 'Map',
        'rerun' => 'Recalculate',
        'remove_mapping' => 'Remove mapping',
        'back' => 'Back to overview',
    ],
    'dialog' => [
        'title' => 'Start a new reconciliation run',
        'hint' => 'At least one export file is required. The run reads Lexoffice in the background; with many customers this takes a few minutes.',
        'telekom' => 'Telekom Cloud Marketplace: purchases.csv',
        'qualityhosting' => 'Quality Hosting: contract export (.xlsx)',
        'pricelist' => 'Quality Hosting: price list (.xlsx, optional)',
        'map' => 'Mapping file (optional)',
        'map_hint' => 'One line per company: “Company;Lexoffice contact UUID” or “Company;customer:<Sqid>”. For everything the run cannot map unambiguously.',
        'reference' => 'Reference date',
        'reference_hint' => 'Periods that started on or before this day count as due. There is no lower bound: everything since the first contract start in the exports is checked.',
        'before' => 'Days before period start',
        'after' => 'Days after period start',
        'window_hint' => 'An invoice belongs to a period if its date falls within this window around the period start.',
        'strict' => 'Strict product matching',
        'strict_hint' => 'Only count invoice line items whose text names the edition. Unchecked, any Microsoft line item of the contact within the window counts when no matching edition is found (collective invoices).',
        'submit' => 'Start run',
    ],
    'field' => [
        'created' => 'Started', 'status' => 'Status', 'sources' => 'Sources', 'reference' => 'Reference date',
        'periods' => 'Periods', 'problems' => 'Flagged', 'open_fee' => 'Open purchase fee', 'unmapped' => 'Unmapped',
        'window' => 'Window', 'files' => 'Files', 'by' => 'By', 'error' => 'Error', 'price_flags' => 'Price flags',
        'company' => 'Company', 'customer' => 'Customer', 'contact' => 'Lexoffice contact', 'mapping' => 'Mapping', 'candidates' => 'Candidates',
        'source' => 'Source', 'edition' => 'Edition', 'period' => 'Period', 'quantity' => 'Quantity', 'purchase' => 'Purchase',
        'vouchers' => 'Invoice(s)', 'unit_net' => 'Net per unit', 'note' => 'Note', 'succession' => 'Succession',
        'voucher' => 'Invoice', 'date' => 'Date', 'position' => 'Line item', 'remaining' => 'Remaining',
        'product' => 'Product', 'term' => 'Term', 'running' => 'Units running', 'contract_price' => 'Purchase (contract)', 'list_price' => 'Purchase (list)',
        'uvp' => 'RRP', 'sales' => 'Sales (median, count)', 'sales_range' => 'Sales min – max', 'margin' => 'Margin vs. list',
        'telekom_from' => 'Telekom from', 'telekom_to' => 'Telekom until', 'successor' => 'QH contract', 'successor_from' => 'QH from',
        'billed_via' => 'Billed via partner (foreign customer)',
        'stored_mapping' => 'Stored mapping',
        'used' => 'Used', 'recognized' => 'Recognised as',
        'valid_from' => 'Price list valid from',
    ],
    'status' => [
        'queued' => 'Queued',
        'running' => 'Running',
        'done' => 'Done',
        'failed' => 'Failed',
    ],
    'section' => [
        'lines' => 'Invoice line items found for the mapped contacts',
        'summary' => 'Summary', 'price' => 'Price check', 'findings' => 'Periods', 'mappings' => 'Mapping marketplace company → Lexoffice contact',
        'extras' => 'Microsoft line items without a due period', 'successions' => 'Successions Telekom → Quality Hosting', 'issues' => 'Notes from the files', 'errors' => 'Read errors', 'files' => 'Files and options',
    ],
    'filter' => [
        'status' => 'Status', 'problems' => 'Flagged only', 'all' => 'All', 'company' => 'Company', 'all_companies' => 'All companies',
    ],
    'empty' => [
        'lines' => 'No invoice line items found.',
        'runs' => 'No run yet. Upload the export files to start the first reconciliation.', 'findings' => 'No periods in this selection.', 'price' => 'No running contracts or no price list uploaded.', 'mappings' => 'No companies.', 'extras' => 'No extra line items.', 'successions' => 'No successions detected.',
    ],
    'price_flag' => [
        'below_list' => 'below purchase', 'below_uvp' => 'below RRP', 'contract_above_list' => 'contract above list', 'no_sales' => 'no invoice data', 'no_list' => 'not in price list',
    ],
    'flash' => [
        'mapping_saved' => 'Mapping saved. Use “Recalculate” to apply it to the report.', 'mapping_removed' => 'Mapping removed.', 'rerun' => 'The run is being recalculated.',
        'created' => 'Run started. The report appears here once Lexoffice has been read.', 'deleted' => 'Run deleted.', 'not_done' => 'The run has not finished yet.',
    ],
    'validation' => [
        'customer_required' => 'Please select a customer.', 'contact_required' => 'Please enter a Lexoffice contact UUID.',
        'need_file' => 'At least one export file (Telekom or Quality Hosting) is required.',
    ],
    'hint' => [
        'lines' => 'Diagnostics: everything the reconciliation saw in Lexoffice for the mapped contacts within the period, with the quantity used. A company without rows here has no invoices for its contact in the period.',
        'run_pending' => 'The run has not finished yet. Refresh the page to see the report.', 'run_failed' => 'The run failed.', 'unmapped' => 'Companies without a mapping can be resolved with a mapping file on the next run.', 'extras' => 'Invoiced without a running subscription, or an edition the reconciliation does not recognise.',
        'mapping' => 'Use “Map” to define per company who receives the invoice: the company itself, a partner or a Lexoffice contact. Stored mappings take precedence over automatic detection.',
        'foreign' => 'End customers of a partner (foreign customers) are checked via the partner: the invoice goes to the partner, who passes it on. Create foreign customers under the partner customer, or add “Company;partner:<name or Sqid>” to the mapping file.',
        'succession' => 'The Telekom term was cut at the Quality Hosting contract start; otherwise every migration would count twice.', 'price' => 'Sales prices come from the matched invoice line items; list purchase price and RRP from the price list for the same term and interval.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'mapping' => [
        'title' => 'Map company',
        'submit' => 'Save mapping',
        'hint' => 'The mapping applies to all future runs of this organisation. Use “Recalculate” afterwards so it takes effect in the report.',
        'mode_label' => 'Billing',
        'mode' => [
            'customer' => 'Directly: the company is the customer',
            'partner' => 'Via a partner (foreign customer)',
            'contact' => 'Lexoffice contact',
        ],
        'mode_hint' => [
            'customer' => 'The invoice goes to this customer itself.',
            'partner' => 'The selected customer receives the invoice and passes it on. The company is created as a foreign customer under it if missing.',
            'contact' => 'Without master data: the invoices of this Lexoffice contact are checked.',
        ],
        'customer' => 'Customer or partner',
        'customer_placeholder' => 'Select customer',
        'contact' => 'Lexoffice contact UUID',
        'contact_hint' => 'Only needed for “Lexoffice contact”; found in the contact\'s Lexoffice URL.',
    ],
    'line' => [
        'header_only' => 'Voucher without line items',
        'microsoft' => 'Microsoft line item',
        'other' => 'Other',
    ],
    'months' => 'mo.',
];
