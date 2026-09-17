<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : classification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dataquality' => [
        'heading' => 'Qualità dei dati: classificazioni obbligatorie mancanti',
        'missing' => ':domain mancante',
    ],
    'error' => [
        'requirementUnmet' => 'Classificazioni obbligatorie mancanti: :domains',
        'requirementMin' => 'Classificazione obbligatoria «:domain»: sono necessarie almeno :min voce/voci, presenti: :actual.',
        'requirementMax' => 'Classificazione obbligatoria «:domain»: sono consentite al massimo :max voce/voci, presenti: :actual.',
    ],
];
