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
            'category' => 'Domaine',
            'all_categories' => 'Tous les domaines',
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
            'lateRecording' => 'Saisie tardive (MiLoG)',
            'sixMonthAverage' => 'Moyenne sur six mois (§ 3 ArbZG)',
            'nightWork' => 'Travail de nuit au-delà de 8 h (§ 6 ArbZG)',
            'substituteRestDay' => 'Jour de repos compensateur manquant (§ 11 ArbZG)',
            'freeSundays' => 'Dimanches libres insuffisants (§ 11 ArbZG)',
            // Feature 144 (MVP-719): Lenk-/Ruhezeiten (VO (EG) 561/2006 / FPersV).
            'dailyDriving' => 'Temps de conduite journalier (art. 6 règl. 561/2006)',
            'weeklyDriving' => 'Temps de conduite hebdomadaire (art. 6 règl. 561/2006)',
            'fortnightDriving' => 'Temps de conduite sur deux semaines (art. 6 règl. 561/2006)',
            'drivingBreakMissing' => 'Pause de conduite manquante (art. 7 règl. 561/2006)',
            'dailyRest' => 'Repos journalier (art. 8 règl. 561/2006)',
            'weeklyRest' => 'Repos hebdomadaire (art. 8 règl. 561/2006)',
        ],
        'unit' => [
            'days' => '{1} :count jour|[2,*] :count jours',
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
            'drivingTime' => 'Temps de conduite',
        ],
        'acknowledged' => 'Infraction mise à jour.',
        'error' => [
            'invalid_status' => 'Statut cible invalide.',
            'not_acknowledgeable' => 'Cette infraction ne peut plus être acquittée.',
            'note_required' => 'Un motif est requis pour « accepté ».',
        ],
    ],
    'milog' => [
        'button' => 'Justificatif MiLoG (douane)',
        'csv' => [
            'employee' => 'Salarié',
            'personnel_number' => 'Matricule',
            'date' => 'Date',
            'start' => 'Début',
            'end' => 'Fin',
            'breaks' => 'Pauses (min)',
            'duration' => 'Durée',
        ],
    ],
    'driving' => [
        'button' => 'Justificatif temps de conduite',
        'title' => 'Justificatif des temps de conduite et de repos',
        'thresholds_note' => 'Temps de conduite/repos (règl. (CE) 561/2006 / FPersV) : max. 9 h de conduite/jour (10 h deux fois par semaine) · 56 h/semaine · 90 h/deux semaines · pause de 45 min après 4,5 h (fractionnable 15 + 30) · repos 11 h/jour (max. 3×/semaine 9 h) · 45 h/semaine (24 h avec compensation).',
        'disclaimer' => 'Les données proviennent des trajets saisis (carnet de bord) avec des véhicules marqués ; les données du tachygraphe/DTCO ne sont pas lues. Pas de conseil juridique.',
        'csv' => [
            'driver' => 'Conducteur',
            'personnel_number' => 'Matricule',
            'date' => 'Date',
            'vehicles' => 'Véhicules',
            'start' => 'Premier départ',
            'end' => 'Dernière arrivée',
            'driving' => 'Temps de conduite',
            'longest_stint' => 'Plus longue période de conduite sans pause',
            'breaks' => 'Pauses (min)',
            'rest_before' => 'Repos précédent',
            'findings' => 'Constats',
        ],
        'badge' => [
            'label' => 'Temps de conduite',
            'remaining' => ':remaining restant',
            'until_break' => 'Pause dans :until',
            'break_due' => 'Pause requise',
            'exhausted' => 'Temps de conduite journalier épuisé',
            'title' => 'Temps de conduite journalier restant :daily (limite :limit) · prochaine pause dans :until · reste hebdomadaire :weekly · deux semaines :fortnight',
        ],
    ],
];
