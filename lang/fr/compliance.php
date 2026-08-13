<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : compliance.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'report' => [
        'title' => 'Conformité du temps de travail',
        'nav' => 'Conformité du temps de travail',
        'subtitle' => 'Infractions à la loi sur le temps de travail sur la base du temps de travail réellement enregistré.',
        'empty' => 'Aucune infraction sur la période.',
        'thresholds_note' => 'Seuils (ArbZG) : max. :daily net/jour · min. :rest de repos · max. moy. :weekly/semaine · pauses obligatoires de 30 min au-delà de 6 h, 45 min au-delà de 9 h.',
        'corrected' => 'corrigé',
        'corrected_hint' => 'Une correction de temps approuvée existe pour ce jour.',
        'drilldown' => 'Ouvrir la clôture journalière',
        'filter' => [
            'kind' => 'Type d’infraction',
            'all' => 'Tous les types',
        ],
        'kpi' => [
            'total' => 'Total des infractions',
            'employees' => 'Employés concernés',
        ],
        'kind' => [
            'maxDailyHours' => 'Durée quotidienne maximale',
            'restPeriod' => 'Temps de repos',
            'breakMissing' => 'Pause obligatoire',
            'maxWeeklyHours' => 'Durée hebdomadaire maximale',
            'frameTime' => 'Plage de travail autorisée',
            'coreTime' => 'Plages fixes',
            'entryBreakMissing' => 'Pause obligatoire (temps projet)',
            'missingCheckout' => 'Pointage de sortie manquant',
            'freeDayStamp' => 'Pointage un jour de repos',
            'absenceStamp' => 'Pointage pendant une absence',
            'attendanceFrameTime' => 'Plage horaire (pointages)',
        ],
        'severity' => [
            'error' => 'Infraction',
            'warning' => 'Avertissement',
        ],
        'col' => [
            'date' => 'Date',
            'kind' => 'Type',
            'value' => 'Valeur',
            'threshold' => 'Seuil',
            'severity' => 'Gravité',
        ],
        'csv' => [
            'employee' => 'Employé',
            'date' => 'Date',
            'kind' => 'Type',
            'severity' => 'Gravité',
            'value' => 'Valeur',
            'threshold' => 'Seuil',
            'corrected' => 'Corrigé',
            'yes' => 'oui',
        ],
    ],
    'history' => [
        'title' => 'Infractions de conformité',
        'nav' => 'Historique des infractions',
        'subtitle' => 'Infractions ArbZG persistées avec état de traitement et acquittement.',
        'to_report' => 'Rapport détaillé',
        'to_dashboard' => 'Tableau de bord',
        'filter' => [
            'status' => 'Statut',
            'all' => 'Tous les statuts',
            'category' => 'Catégorie',
        ],
        'col' => [
            'employee' => 'Employé',
            'status' => 'Statut',
        ],
        'empty' => 'Aucune infraction persistée.',
        'note_placeholder' => 'Motif (obligatoire pour « accepté »)',
        'btn' => [
            'acknowledge' => 'Acquitter',
            'accept' => 'Accepter',
            'correction' => 'Demande de correction',
        ],
        'category' => [
            'arbzg' => 'ArbZG',
            'plausibility' => 'Cas non clarifiés',
        ],
        'acknowledged' => 'Infraction mise à jour.',
        'error' => [
            'invalid_status' => 'Statut cible invalide.',
            'not_acknowledgeable' => 'Cette infraction ne peut plus être acquittée.',
            'note_required' => 'Un motif est requis pour « accepté ».',
        ],
    ],
];
