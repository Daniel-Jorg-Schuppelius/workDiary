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
        'title' => 'Évaluation IFRS 16 / HGB',
        'disclaimer' => 'Référence préparatoire sans engagement comptable ; l\'appréciation relève de la comptabilité ou du conseil fiscal.',
        'ifrs16' => 'IFRS 16 (preneur)',
        'hgb' => 'HGB/fiscal (attribution)',
        'ratio' => 'Durée de base / durée d\'utilité',
        'months' => 'mois',
        'reasons' => 'Justification',
        'assessed_at' => 'Évalué le :date',
        'assess' => 'Évaluer',
        'ifrs16_result' => [
            'short_term' => 'court terme — exemption possible',
            'low_value' => 'faible valeur — exemption possible',
            'right_of_use' => 'comptabiliser droit d\'utilisation et dette locative',
        ],
        'hgb_result' => [
            'lessor' => 'attribué au bailleur',
            'lessee' => 'attribué au preneur',
            'unknown' => 'non évaluable (données manquantes)',
        ],
        'reason' => [
            'special_lease' => 'leasing spécial',
            'missing_inputs' => 'durée ou durée d\'utilité manquante',
            'term_below_40' => 'durée de base inférieure à 40 % de la durée d\'utilité',
            'term_above_90' => 'durée de base supérieure à 90 % de la durée d\'utilité',
            'bargain_option' => 'option d\'achat inférieure à la valeur résiduelle',
            'term_within' => 'durée de base entre 40 % et 90 %, pas d\'option avantageuse',
        ],
        'field' => [
            'useful_life_months' => 'Durée d\'utilité (mois)',
            'asset_value_amount' => 'Valeur à neuf',
            'is_special_lease' => 'Leasing spécial',
        ],
        'flash' => [
            'saved' => 'Évaluation enregistrée.',
        ],
    ],
];
