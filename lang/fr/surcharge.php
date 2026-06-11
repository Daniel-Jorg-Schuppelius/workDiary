<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : surcharge.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'rules' => 'Règles de majoration',
        'rules_subtitle' => 'Majorations de nuit, de week-end et de jours fériés par organisation : plage horaire, pourcentage et rubrique de paie pour le transfert.',
        'rules_help' => 'Comment fonctionnent les règles de majoration ?',
        'rules_help_text' => 'Chaque règle décrit les périodes ouvrant droit à majoration (plage de nuit, samedi, dimanche, jour férié ou plage personnalisée) avec un pourcentage et une rubrique de paie. Lors de l\'export des temps, les présences sont découpées en conséquence et restituées en lignes d\'export supplémentaires par jour. En cas de chevauchement, le pourcentage le plus élevé l\'emporte — les majorations ne s\'additionnent pas.',
        'create_rule' => 'Créer une règle de majoration',
        'edit_rule' => 'Modifier la règle de majoration',
        'empty' => 'Aucune règle de majoration',
        'export_summary' => 'Majorations par collaborateur·rice et rubrique de paie',
    ],

    'field' => [
        'basics' => 'Données de base',
        'code' => 'Code',
        'code_help' => 'Clé courte et unique (minuscules, chiffres, ._-), p. ex. « night ».',
        'label' => 'Libellé',
        'label_placeholder' => 'p. ex. Majoration de nuit',
        'kind' => 'Type',
        'kind_help' => 'Nuit/Personnalisé utilisent la plage horaire ; samedi, dimanche et jour férié s\'appliquent à la journée entière.',
        'window' => 'Plage horaire',
        'window_help' => 'Uniquement pour Nuit/Personnalisé. Les plages passant minuit (p. ex. 23:00–06:00) sont autorisées et découpées correctement.',
        'window_start' => 'Plage de',
        'window_end' => 'Plage à',
        'whole_day' => 'journée entière',
        'percentage' => 'Majoration (%)',
        'payroll' => 'Transfert de paie',
        'wage_type_code' => 'Rubrique de paie',
        'wage_type_code_help' => 'Numéro de rubrique pour DATEV/Lexware (p. ex. 2010). Vide = exporter sans rubrique.',
        'priority' => 'Priorité',
        'priority_help' => 'Départage en cas de pourcentage identique : la priorité la plus élevée gagne.',
        'validity' => 'Validité',
        'valid_from' => 'Valable à partir du',
        'valid_until' => 'Valable jusqu\'au',
        'unlimited' => 'illimitée',
        'active' => 'Active',
        'rule_active' => 'La règle est active',
        'hours' => 'Heures',
        'yes' => 'Oui',
        'no' => 'Non',
    ],

    'action' => [
        'create' => 'Créer',
        'edit' => 'Modifier',
        'save' => 'Enregistrer',
        'delete' => 'Supprimer',
        'delete_confirm' => 'Supprimer vraiment cette règle de majoration ? Les exports existants restent inchangés.',
    ],

    'flash' => [
        'created' => 'Règle de majoration créée.',
        'updated' => 'Règle de majoration mise à jour.',
        'deleted' => 'Règle de majoration supprimée.',
    ],
];
