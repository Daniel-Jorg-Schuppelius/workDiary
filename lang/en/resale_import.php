<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : resale_import.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Reselling register (feature 152): messages of the import readers (Telekom CSV,
// Quality Hosting XLSX/PDF, generic list). File names only, never paths.
return [
    'column' => [
        'company' => 'Company',
        'product' => 'Product',
        'start' => 'Start',
    ],
    'file' => [
        'unreadable' => 'File not readable: :file',
        'xlsx_unreadable' => 'XLSX file not readable: :file (:reason)',
        'no_header' => 'CSV without header row: :file',
        'no_sheet' => 'XLSX without worksheet: :file',
        'missing_columns' => 'Required columns missing: :columns',
    ],
    'row' => [
        'too_many_fields' => 'Line :line: :found fields instead of :expected (unescaped delimiter?) - skipped.',
        'missing_company_or_product' => 'Line :line: company or product missing - skipped.',
        'missing_company_or_entitlement' => 'Line :line: company or entitlement missing - skipped.',
        'start_unreadable' => 'Line :line (:company): start ":value" not readable - skipped.',
        'end_unreadable' => 'Line :line (:company): end ":value" not readable - skipped.',
        'end_before_start' => 'Line :line (:company): end is not after the start - skipped.',
        'unknown_frequency' => 'Line :line (:company): unknown billing frequency ":value" - skipped.',
        'quantity_invalid' => 'Line :line (:company): quantity ":value" not readable or not a positive integer - skipped.',
        'unknown_currency' => 'Line :line (:company): unknown currency ":value" - skipped.',
        'duplicate_id' => 'Line :line (:company): identifier ":value" duplicates line :other - skipped.',
        'term_unreadable' => 'Line :line (:company): term ":value" not readable - default term of the billing frequency used.',
        'fee_unreadable' => 'Line :line (:company): fee ":value" not readable - skipped.',
        'dates_unreadable' => 'Line :line (:company): date not readable (":start" / ":end") - skipped.',
        'contract_end_before_start' => 'Line :line (:company): contract end is not after the start - skipped.',
        'no_contract' => 'Line :line (:company): no contract number - skipped.',
        'total_unreadable' => 'Line :line (:company): total price ":value" not readable - skipped.',
        'contract_start_unreadable' => 'Line :line (:company): contract start ":value" not readable - skipped.',
        'status_end_before_start' => 'Line :line (:company): contract end from status is not after the start - skipped.',
        'status_unknown' => 'Line :line (:company): contract status ":value" unknown - treated as running.',
    ],
    'pricelist' => [
        'unreadable' => 'Price list not readable: :file',
        'unreadable_reason' => 'Price list not readable: :file (:reason)',
        'no_sheet' => 'Price list without sheet "Preisdaten" (column "Produkttarif" missing).',
        'missing_columns' => 'Required price list columns missing: :columns',
        'row_invalid' => 'Price list line :line (:product): term, interval or price not readable - skipped.',
        'valid_from_unreadable' => 'Price list line :line (:product): "Gültig ab" ":value" not readable - validity from cover sheet or import date.',
        'no_valid_from' => 'Price list without validity date (neither cover sheet nor column "Gültig ab") - the import date is used as validity start.',
    ],
    'invoice' => [
        'unreadable' => 'PDF file :file not readable (:reason).',
        'no_text' => 'No text could be extracted from :file (not even via OCR).',
        'no_number' => 'No invoice/credit note number found.',
        'no_date' => 'No document date found.',
        'total_mismatch' => 'Sum of the line items (:lines) differs from the net total of the document (:net) - line items are missing or were split differently.',
    ],
];
