<?php
/*
 * Created on   : Sun Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reporting.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'target' => [
        'nav' => 'Valeurs cibles',
        'title' => 'Valeurs cibles & références',
        'subtitle' => 'Définissez des valeurs cibles par indicateur – les rapports affichent la cible, le réel et l\'écart.',
        'create' => 'Ajouter une valeur cible',
        'edit' => 'Modifier la valeur cible',
        'empty' => 'Aucune valeur cible définie pour le moment.',
        'metric_label' => 'Indicateur',
        'scope_label' => 'Portée',
        'scope_ref' => 'Objet de référence',
        'scope_ref_hint' => 'À sélectionner uniquement pour un client/projet/employé.',
        'value_label' => 'Valeur cible',
        'period_label' => 'Période de référence',
        'valid_from' => 'Valide à partir du',
        'valid_until' => 'Valide jusqu\'au',
        'note_label' => 'Note',
        'created' => 'La valeur cible a été créée.',
        'updated' => 'La valeur cible a été mise à jour.',
        'deleted' => 'La valeur cible a été supprimée.',
        'delete_confirm' => 'Supprimer vraiment cette valeur cible ?',
        'none' => '–',
        'soll' => 'Cible',
        'ist' => 'Réel',
        'deviation' => 'Écart',
        'met' => 'atteinte',
        'missed' => 'manquée',
        'no_target' => 'Aucune cible',
        'metric' => [
            'contributionMargin' => 'Marge sur coûts variables (%)',
            'billableRate' => 'Taux facturable (%)',
            'reworkShare' => 'Part de retouche (%)',
            'slaComplianceRate' => 'Taux de respect des SLA (%)',
            'utilization' => 'Taux d\'utilisation (%)',
        ],
        'scope' => [
            'org' => 'Organisation (global)',
            'customer' => 'Client',
            'project' => 'Projet',
            'user' => 'Employé',
        ],
        'period' => [
            'month' => 'Mois',
            'quarter' => 'Trimestre',
            'year' => 'Année',
        ],
    ],

    'cohort' => [
        'nav' => 'Comparaison de cohortes',
        'title' => 'Comparaison de cohortes (avant/après formation)',
        'subtitle' => 'Compare un indicateur par employé pour la période avant et après l\'obtention d\'une formation.',
        'qualification' => 'Formation / qualification',
        'metric' => [
            'billableRate' => 'Taux facturable (%)',
            'reworkShare' => 'Part de retouche (%)',
        ],
        'metric_label' => 'Indicateur',
        'window' => 'Fenêtre de comparaison (jours)',
        'choose' => 'Veuillez sélectionner une formation.',
        'member' => 'Employé',
        'acquired_on' => 'Obtenue le',
        'before' => 'Avant',
        'after' => 'Après',
        'delta' => 'Δ',
        'improved' => 'Amélioré',
        'no_date' => 'pas de date d\'obtention',
        'no_date_hint' => 'Sans date d\'obtention enregistrée (qualification « valide à partir du »), aucune répartition avant/après ne peut être établie.',
        'no_data_window' => 'Pas assez de saisies de temps dans l\'une des fenêtres.',
        'aggregate' => 'Cohorte totale (moyenne)',
        'members_with_date' => 'avec date d\'obtention',
        'members_without_date' => 'sans date d\'obtention',
        'improved_count' => 'améliorés',
        'data_note' => 'Source de la date d\'obtention : le « valide à partir du » de l\'affectation de qualification. Les indicateurs proviennent des mêmes champs de saisie de temps (facturable/non facturable) que la vue rentabilité.',
    ],
];
