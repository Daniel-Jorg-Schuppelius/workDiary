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
        'title' => 'Evaluación NIIF 16 / HGB',
        'disclaimer' => 'Referencia preparatoria sin compromiso contable; la valoración corresponde a contabilidad o asesoría fiscal.',
        'ifrs16' => 'NIIF 16 (arrendatario)',
        'hgb' => 'HGB/fiscal (imputación)',
        'ratio' => 'Plazo básico / vida útil',
        'months' => 'meses',
        'reasons' => 'Motivo',
        'assessed_at' => 'Evaluado el :date',
        'assess' => 'Evaluar',
        'ifrs16_result' => [
            'short_term' => 'corto plazo — exención posible',
            'low_value' => 'bajo valor — exención posible',
            'right_of_use' => 'reconocer derecho de uso y pasivo por arrendamiento',
        ],
        'hgb_result' => [
            'lessor' => 'imputado al arrendador',
            'lessee' => 'imputado al arrendatario',
            'unknown' => 'no evaluable (faltan datos)',
        ],
        'reason' => [
            'special_lease' => 'arrendamiento especial',
            'missing_inputs' => 'falta plazo o vida útil',
            'term_below_40' => 'plazo básico inferior al 40 % de la vida útil',
            'term_above_90' => 'plazo básico superior al 90 % de la vida útil',
            'bargain_option' => 'opción de compra inferior al valor residual',
            'term_within' => 'plazo básico entre el 40 % y el 90 %, sin opción ventajosa',
        ],
        'field' => [
            'useful_life_months' => 'Vida útil (meses)',
            'asset_value_amount' => 'Valor a nuevo',
            'is_special_lease' => 'Arrendamiento especial',
        ],
        'flash' => [
            'saved' => 'Evaluación guardada.',
        ],
    ],
];
