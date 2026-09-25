<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : claims.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'pattern' => [
        'title' => 'Schémas remarquables (défauts en série, lots)',
        'hint' => 'Groupes d’au moins :threshold réclamations dans la période. Une indication, pas une décision.',
        'none' => 'Aucun schéma remarquable dans la période.',
        'rule_label' => 'Règle',
        'group' => 'Groupe',
        'cases' => 'Dossiers',
        'count' => 'Nombre',
        'rule' => [
            'lot' => 'Lot',
            'article_defect' => 'Article × type de défaut',
            'article_cause' => 'Article × cause',
            'supplier_defect' => 'Fournisseur × type de défaut',
            'entry_type_cause' => 'Type de commande × cause',
        ],
        'notify_title' => 'Schéma de réclamations remarquable : :label',
        'notify_message' => ':count réclamations en :days jours (:rule).',
    ],
];
