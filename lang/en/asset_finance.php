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
        'title' => 'IFRS 16 / HGB assessment',
        'disclaimer' => 'Preparatory reference without accounting commitment; the assessment is made by accounting or tax advisers.',
        'ifrs16' => 'IFRS 16 (lessee)',
        'hgb' => 'HGB/tax (attribution)',
        'ratio' => 'Basic lease term / useful life',
        'months' => 'months',
        'reasons' => 'Reasoning',
        'assessed_at' => 'Assessed on :date',
        'assess' => 'Assess',
        'ifrs16_result' => [
            'short_term' => 'short-term — exemption possible',
            'low_value' => 'low value — exemption possible',
            'right_of_use' => 'recognise right-of-use asset and lease liability',
        ],
        'hgb_result' => [
            'lessor' => 'attributed to the lessor',
            'lessee' => 'attributed to the lessee',
            'unknown' => 'cannot be assessed (data missing)',
        ],
        'reason' => [
            'special_lease' => 'special lease',
            'missing_inputs' => 'term or useful life missing',
            'term_below_40' => 'basic term below 40 % of useful life',
            'term_above_90' => 'basic term above 90 % of useful life',
            'bargain_option' => 'purchase option below residual value',
            'term_within' => 'basic term between 40 % and 90 %, no bargain option',
        ],
        'field' => [
            'useful_life_months' => 'Useful life (months)',
            'asset_value_amount' => 'Value when new',
            'is_special_lease' => 'Special lease',
        ],
        'flash' => [
            'saved' => 'Assessment saved.',
        ],
    ],
];
