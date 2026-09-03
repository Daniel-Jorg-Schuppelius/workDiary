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
        'reference_hint' => 'Periods that started on or before this day count as due.',
        'before' => 'Days before period start',
        'after' => 'Days after period start',
        'window_hint' => 'An invoice belongs to a period if its date falls within this window around the period start.',
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
        'valid_from' => 'Price list valid from',
    ],
    'status' => [
        'queued' => 'Queued',
        'running' => 'Running',
        'done' => 'Done',
        'failed' => 'Failed',
    ],
    'section' => [
        'summary' => 'Summary', 'price' => 'Price check', 'findings' => 'Periods', 'mappings' => 'Mapping marketplace company → Lexoffice contact',
        'extras' => 'Microsoft line items without a due period', 'successions' => 'Successions Telekom → Quality Hosting', 'issues' => 'Notes from the files', 'errors' => 'Read errors', 'files' => 'Files and options',
    ],
    'filter' => [
        'status' => 'Status', 'problems' => 'Flagged only', 'all' => 'All', 'company' => 'Company', 'all_companies' => 'All companies',
    ],
    'empty' => [
        'runs' => 'No run yet. Upload the export files to start the first reconciliation.', 'findings' => 'No periods in this selection.', 'price' => 'No running contracts or no price list uploaded.', 'mappings' => 'No companies.', 'extras' => 'No extra line items.', 'successions' => 'No successions detected.',
    ],
    'price_flag' => [
        'below_list' => 'below purchase', 'below_uvp' => 'below RRP', 'contract_above_list' => 'contract above list', 'no_sales' => 'no invoice data', 'no_list' => 'not in price list',
    ],
    'flash' => [
        'created' => 'Run started. The report appears here once Lexoffice has been read.', 'deleted' => 'Run deleted.', 'not_done' => 'The run has not finished yet.',
    ],
    'validation' => [
        'need_file' => 'At least one export file (Telekom or Quality Hosting) is required.',
    ],
    'hint' => [
        'run_pending' => 'The run has not finished yet. Refresh the page to see the report.', 'run_failed' => 'The run failed.', 'unmapped' => 'Companies without a mapping can be resolved with a mapping file on the next run.', 'extras' => 'Invoiced without a running subscription, or an edition the reconciliation does not recognise.',
        'succession' => 'The Telekom term was cut at the Quality Hosting contract start; otherwise every migration would count twice.', 'price' => 'Sales prices come from the matched invoice line items; list purchase price and RRP from the price list for the same term and interval.',
    ],
    'source' => [
        'telekom' => 'Telekom', 'qualityhosting' => 'Quality Hosting',
    ],
    'months' => 'mo.',
];
