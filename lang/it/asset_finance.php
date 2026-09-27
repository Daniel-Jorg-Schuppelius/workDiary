<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : asset_finance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Leasing/Finanzierung: IFRS-16-/HGB-Einschätzung (MVP-947).
return [
    'classification' => [
        'title' => 'Valutazione IFRS 16 / HGB',
        'disclaimer' => 'Riferimento preparatorio senza impegno contabile; la valutazione spetta alla contabilità o al consulente fiscale.',
        'ifrs16' => 'IFRS 16 (locatario)',
        'hgb' => 'HGB/fiscale (attribuzione)',
        'ratio' => 'Durata base / vita utile',
        'months' => 'mesi',
        'reasons' => 'Motivazione',
        'assessed_at' => 'Valutato il :date',
        'assess' => 'Valuta',
        'ifrs16_result' => [
            'short_term' => 'breve termine — esenzione possibile',
            'low_value' => 'basso valore — esenzione possibile',
            'right_of_use' => 'rilevare diritto d\'uso e passività del leasing',
        ],
        'hgb_result' => [
            'lessor' => 'attribuito al locatore',
            'lessee' => 'attribuito al locatario',
            'unknown' => 'non valutabile (dati mancanti)',
        ],
        'reason' => [
            'special_lease' => 'leasing speciale',
            'missing_inputs' => 'manca durata o vita utile',
            'term_below_40' => 'durata base inferiore al 40 % della vita utile',
            'term_above_90' => 'durata base superiore al 90 % della vita utile',
            'bargain_option' => 'opzione di acquisto inferiore al valore residuo',
            'term_within' => 'durata base tra 40 % e 90 %, nessuna opzione vantaggiosa',
        ],
        'field' => [
            'useful_life_months' => 'Vita utile (mesi)',
            'asset_value_amount' => 'Valore a nuovo',
            'is_special_lease' => 'Leasing speciale',
        ],
        'flash' => [
            'saved' => 'Valutazione salvata.',
        ],
    ],
];
