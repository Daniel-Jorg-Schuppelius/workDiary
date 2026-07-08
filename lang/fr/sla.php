<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sla.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'remaining' => 'encore :min min',
    'overdue_by' => ':min min de retard',
    'diary_panel_heading' => 'Tickets de service liés',

    'report' => [
        'title' => 'Rapport SLA',
        'nav' => 'SLA',
        'subtitle' => 'Violations de SLA, taux de respect et causes sur la période.',
        'total_tickets' => 'Tickets avec SLA',
        'violations' => 'Violations',
        'met' => 'Respectés',
        'compliance_rate' => 'Taux de respect',
        'by_kind' => 'Par type',
        'by_priority' => 'Par priorité',
        'by_customer' => 'Par client',
        'by_cause' => 'Par cause',
        'kind' => 'Type',
        'cause' => 'Cause',
        'no_causes' => 'Aucune cause enregistrée.',
        'no_violations' => 'Aucune violation sur la période.',
        'violation_list' => 'Liste des violations',
        'target' => 'Cible',
        'breached_at' => 'Détecté',
        'overdue' => 'Retard (min)',
        'status' => 'Statut',
        'acknowledged_badge' => 'Acquitté',
        'open_badge' => 'Ouvert',
        'acknowledge_btn' => 'Acquitter',
        'acknowledged' => 'Violation acquittée.',
        'no_customer' => 'Sans client',
        'cause_unspecified' => 'Non précisé',
        'section' => 'Section',
        'metric' => 'Indicateur',
        'value' => 'Valeur',
        'overview' => 'Aperçu',
        'quotas_heading' => 'Quotas de temps inclus',
        'no_quotas' => 'Aucun quota configuré.',
        'quota_usage' => ':consumed / :included h (:period)',
        'quota_over' => ':min min au-dessus du quota',
    ],
];
