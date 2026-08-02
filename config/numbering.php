<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : numbering.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    /*
     * Default-Format pro Nummernkreis (Scope). Kann pro Organisation in
     * `number_formats` überschrieben werden.
     *
     * Felder:
     *  - prefix            : freier Text-Präfix (z. B. "ST" oder "R")
     *  - prefix_separator  : Trennzeichen nach dem Präfix (z. B. "-" oder "")
     *  - include_year      : ob das Jahr (YYYY) eingefügt wird
     *  - year_separator    : Trennzeichen zwischen Jahr und laufender Nummer
     *  - padding           : Mindestbreite des numerischen Anteils
     *  - reset_per_year    : ob die Sequenz pro Jahr zurückgesetzt wird
     *  - starts_at         : initialer Counter (last_value), nächste Nummer = starts_at + 1
     */
    'defaults' => [
        'claim' => [
            'prefix' => 'REK',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'rma' => [
            'prefix' => 'RMA',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'rental' => [
            'prefix' => 'VER',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'asset_finance' => [
            'prefix' => 'LEA',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'contract' => [
            'prefix' => 'VTR',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        // Datenschutz-Fallakten (Vollreview W1.1): lösen die früheren
        // count-basierten nextNumber()-Kopien in den Privacy-Services ab.
        'privacy_incident' => [
            'prefix' => 'DSV',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'data_subject_request' => [
            'prefix' => 'DSR',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'disposal' => [
            'prefix' => 'ENT',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'problem_report' => [
            'prefix' => 'PR',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'service_ticket' => [
            'prefix' => 'ST',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 5,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'asset' => [
            'prefix' => 'AS',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'article' => [
            'prefix' => 'ART',
            'prefix_separator' => '-',
            'include_year' => false,
            'year_separator' => '-',
            'padding' => 5,
            'reset_per_year' => false,
            'starts_at' => 0,
        ],
        'manufacturing_order' => [
            'prefix' => 'FA',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 5,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'serial' => [
            'prefix' => 'SN',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 6,
            'reset_per_year' => false,
            'starts_at' => 0,
            'check_digit' => true, // Luhn-Prüfziffer anhängen (scann-/tippsicher)
        ],
        'purchase_order' => [
            'prefix' => 'BE',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 5,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'customer' => [
            'prefix' => 'K',
            'prefix_separator' => '-',
            'include_year' => false,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => false,
            'starts_at' => 0,
        ],
        'supplier' => [
            'prefix' => 'L',
            'prefix_separator' => '-',
            'include_year' => false,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => false,
            'starts_at' => 0,
        ],
        'invoice' => [
            'prefix' => 'R',
            'prefix_separator' => '',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'credit_note' => [
            'prefix' => 'G',
            'prefix_separator' => '',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'quote' => [
            'prefix' => 'AN',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'proforma' => [
            'prefix' => 'PF',
            'prefix_separator' => '-',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
        'cancellation' => [
            'prefix' => 'S',
            'prefix_separator' => '',
            'include_year' => true,
            'year_separator' => '-',
            'padding' => 4,
            'reset_per_year' => true,
            'starts_at' => 0,
        ],
    ],
];
