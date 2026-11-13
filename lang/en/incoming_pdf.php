<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : incoming_pdf.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Combined PDFs and GiroCode in incoming invoices (Feature 208, MVP-1662/1663).
return [
    'split' => [
        'title' => 'Split combined PDF',
        'card_title' => 'Combined PDF',
        'gate' => 'Combined PDF: split it first or confirm it as one invoice.',
        'part_description' => 'Part of “:title”, pages :from–:to',
        'document_title' => 'Combined PDF :file (:count invoices)',
        'document_description' => 'Unchanged original, split into :count incoming invoices.',
        'state' => [
            'pending' => 'The pages are being checked for several invoices. Transfer and payment wait until then.',
            'proposed' => 'The PDF probably contains :count invoices. Transfer and payment wait until you decide.',
            'single' => 'The :pages pages probably belong to one invoice. If there are several after all, split them by hand.',
            'dismissed' => 'Confirmed as one invoice.',
            'splitting' => 'The split is running in the background.',
            'too_large' => 'More than :max pages — no check for several invoices.',
            'failed' => 'The check for several invoices failed; the incoming invoice counts as one invoice.',
        ],
        'action' => [
            'reset' => 'Reset split',
            'reset_confirm' => 'The split is taking unusually long. Reset it and try again?',
            'open' => 'Check boundaries',
            'split' => 'Split',
            'dismiss' => 'Is one invoice',
            'dismiss_confirm' => 'Keep the incoming invoice as one invoice? Transfer and payment continue afterwards.',
        ],
        'dialog' => [
            'intro' => 'Mark the pages on which a new invoice starts. Each invoice becomes its own incoming invoice with its own partial PDF; the original stays unchanged.',
            'page' => 'Page :page',
            'starts_here' => 'New invoice starts here',
            'first_page' => 'First invoice',
            'no_image' => 'No preview',
            'images_missing' => 'Preview images are missing — you can still mark the pages.',
            'signals' => 'Hints',
        ],
        'signal' => [
            'page_one' => '“Page 1 of …”',
            'continued' => 'continuation page',
            'new_number' => 'new invoice number',
            'same_number' => 'same invoice number',
            'new_sender' => 'new sender',
            'after_total' => 'after a totals block',
            'new_letterhead' => 'different letterhead',
        ],
        'blocked' => [
            'split' => 'This incoming invoice was split as a combined PDF — its parts are processed instead.',
            'part' => 'This incoming invoice is already part of a split combined PDF.',
            'no_bundle' => 'There is no open combined-PDF check for this incoming invoice.',
            'pending' => 'The pages are still being checked.',
            'running' => 'The split is already running.',
            'too_large' => 'The PDF is too large to split.',
            'status' => 'Only open incoming invoices (received or with a query) can be split.',
            'transferred' => 'The incoming invoice has already been transferred to accounting — split it there.',
            'payment' => 'The incoming invoice is in a payment run.',
            'linked' => 'The incoming invoice has retentions or links (fixed asset, open item, recourse, credit note).',
            'hold' => 'A legal hold applies to the incoming invoice.',
        ],
        'error' => [
            'too_many_parts' => 'At most :max invoices per combined PDF.',
            'parts_too_large' => 'The partial PDFs together would be too large — please split the file before uploading.',
            'stalled' => 'The split did not finish and has been reset.',
            'invalid_page' => 'The page numbers do not match the document.',
            'single' => 'Mark at least one more start page — otherwise it stays one invoice.',
            'original_missing' => 'The original is missing from the document management.',
            'original_changed' => 'The stored original no longer matches the hash of the incoming invoice — not split.',
            'tool_missing' => 'A PDF tool for extracting pages is missing (mutool, pdftk or poppler).',
            'extract_failed' => 'Pages :from–:to could not be extracted.',
            'nothing_created' => 'All parts already exist as incoming invoices — nothing was split.',
            'failed' => 'The split failed.',
            'original_protected' => 'The original of a combined PDF stays as long as one of its parts exists.',
        ],
        'flash' => [
            'reset' => 'Split reset.',
            'done' => 'Combined PDF split into :count invoices.',
            'queued' => 'Split into :count invoices started — the incoming invoices will appear in the list shortly.',
            'dismissed' => 'Kept as one invoice.',
        ],
        'parts' => [
            'title' => 'Parts of this combined PDF',
            'intro' => 'The original stays unchanged; each part is its own incoming invoice with its own partial PDF.',
            'pages' => 'Pages :from–:to',
            'protected' => 'The original cannot be deleted as long as parts exist.',
        ],
        'part_of' => 'Part of a combined PDF, pages :from–:to.',
        'open_original' => 'Open original',
    ],
    'girocode' => [
        'title' => 'GiroCode',
        'pending' => 'The GiroCode is being read in the background.',
        'status' => [
            'found' => 'GiroCode read (page :page).',
            'none' => 'No GiroCode found.',
            'unreadable' => 'GiroCode not readable.',
            'invalid' => 'QR code with GiroCode identifier but invalid content.',
        ],
        'reason' => [
            'page_size' => 'The page dimensions are unreadable or too large to rasterize.',
            'changed' => 'The stored original no longer matches the incoming invoice.',
            'tool_missing' => 'The reading tool (zbarimg) is missing on the server.',
            'too_large' => 'The file is too large to read.',
            'scan_failed' => 'An error occurred while reading.',
        ],
        'field' => [
            'name' => 'Beneficiary',
            'iban' => 'IBAN',
            'bic' => 'BIC',
            'amount' => 'Amount',
            'reference' => 'Payment reference',
        ],
        'iban_applied' => 'The IBAN from the GiroCode was taken over because the document did not state one.',
        'finding' => [
            'new_bank_details' => 'The IBAN only comes from the GiroCode; no bank account is stored for the supplier to compare it with.',
            'iban_differs_invoice' => 'The IBAN in the GiroCode differs from the IBAN of the invoice.',
            'iban_differs_supplier' => 'The IBAN in the GiroCode does not belong to any stored bank account of the supplier.',
            'multiple_codes' => 'The document carries several GiroCodes with different IBANs.',
            'iban_invalid' => 'The check digits of the IBAN in the GiroCode are wrong — the IBAN was not taken over.',
            'amount_differs' => 'The amount in the GiroCode differs from the gross amount.',
            'name_differs' => 'The beneficiary name in the GiroCode does not match the supplier.',
            'unreadable' => 'GiroCode not readable.',
            'invalid' => 'GiroCode invalid.',
        ],
        'blocking' => 'Different bank details — a known fraud pattern. Blocked in the payment run until someone with release permission confirms the difference.',
        'confirmed' => 'Difference confirmed on :date.',
    ],
];
