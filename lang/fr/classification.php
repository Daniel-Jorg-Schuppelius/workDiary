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
        'heading' => 'Qualité des données : classifications obligatoires manquantes',
        'missing' => ':domain manquant',
    ],
    'error' => [
        'requirementUnmet' => 'Classifications obligatoires manquantes : :domains',
        'requirementMin' => 'Classification obligatoire « :domain » : au moins :min entrée(s) requise(s), présentes : :actual.',
        'requirementMax' => 'Classification obligatoire « :domain » : au maximum :max entrée(s) autorisée(s), présentes : :actual.',
        'referenced' => 'La valeur « :label » est encore utilisée (:usage) et ne peut pas être supprimée. Désactivez-la plutôt : elle disparaît des nouvelles sélections, les enregistrements existants la conservent.',
    ],
    'action' => [
        'deactivate' => 'Désactiver',
        'deactivate_confirm' => 'Désactiver cette valeur ? Elle ne pourra plus être sélectionnée, les enregistrements existants la conservent.',
    ],
    'flash' => [
        'deactivated' => '« :label » a été désactivée.',
    ],
    'reference' => [
        'time_entries' => 'Saisies de temps',
        'claim_cases' => 'Réclamations',
        'products' => 'Articles',
        'classifiables' => 'Affectations aux enregistrements',
        'import_value_mappings' => 'Correspondances d\'import',
        'assets' => 'Actifs',
    ],
];
