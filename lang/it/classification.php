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
        'referenced' => 'Il valore «:label» è ancora in uso (:usage) e non può essere eliminato. Lo disattivi invece: scompare dalle nuove selezioni, i record esistenti lo mantengono.',
    ],
    'action' => [
        'deactivate' => 'Disattivare',
        'deactivate_confirm' => 'Disattivare questo valore? Non sarà più selezionabile; i record esistenti lo mantengono.',
    ],
    'flash' => [
        'deactivated' => '«:label» è stato disattivato.',
    ],
    'reference' => [
        'time_entries' => 'Registrazioni di tempo',
        'claim_cases' => 'Reclami',
        'products' => 'Articoli',
        'classifiables' => 'Assegnazioni ai record',
        'import_value_mappings' => 'Mappature di importazione',
        'assets' => 'Asset',
    ],
];
