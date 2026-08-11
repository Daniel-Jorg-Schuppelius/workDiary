<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : costcenter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => "Règles de centres de coûts",
        'rules_subtitle' => "Centres de coûts pour l'export de temps vérifié : par utilisateur, par équipe ou comme valeur par défaut de l'organisation.",
        'rules_help' => "Comment fonctionnent les règles de centres de coûts ?",
        'rules_help_text' => "Lors de l'export de temps, chaque ligne reçoit le centre de coûts du collaborateur : une règle utilisateur gagne d'abord, puis la règle d'équipe avec la priorité la plus élevée, enfin la valeur par défaut de l'organisation. L'interface de contrôle permet de remplacer le centre de coûts par ligne.",
        'create_rule' => "Créer une règle de centre de coûts",
        'edit_rule' => "Modifier la règle de centre de coûts",
        'empty' => "Aucune règle de centre de coûts",
    ],

    'field' => [
        'basics' => "Règle",
        'source' => "Source",
        'source_help' => "Les règles utilisateur priment sur les règles d'équipe ; sans correspondance, la valeur par défaut de l'organisation s'applique.",
        'source_default' => "Valeur par défaut de l'organisation",
        'source_user' => "Utilisateur",
        'source_team' => "Équipe",
        'user' => "Utilisateur",
        'team' => "Équipe",
        'choose' => "– veuillez choisir –",
        'cost_center' => "Centre de coûts",
        'cost_center_master' => "Centre de coûts des données de base",
        'cost_center_master_free' => "– saisie libre –",
        'cost_center_master_help' => "La sélection reprend le code des données de base ; sans sélection, le code saisi librement s'applique.",
        'priority' => "Priorité",
        'priority_help' => "Départage entre plusieurs règles d'équipe : la priorité la plus élevée gagne.",
    ],

    'action' => [
        'create' => "Créer",
        'edit' => "Modifier",
        'save' => "Enregistrer",
        'delete' => "Supprimer",
        'delete_confirm' => "Supprimer vraiment cette règle de centre de coûts ? Les exports existants restent inchangés.",
    ],

    'flash' => [
        'created' => "Règle de centre de coûts créée.",
        'updated' => "Règle de centre de coûts mise à jour.",
        'deleted' => "Règle de centre de coûts supprimée.",
    ],
];
