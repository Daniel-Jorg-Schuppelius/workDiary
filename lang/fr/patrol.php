<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : patrol.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Wachrundgänge (Phase 156).
return [
    'report' => [
        'title' => 'Rapport de rondes',
        'subtitle' => 'Rondes par itinéraire et site sur la période — prévues, à l’heure, en retard et manquées.',
        'expected' => 'Points prévus',
        'onTime' => 'à l’heure',
        'late' => 'en retard',
        'missed' => 'manqué',
        'note' => 'Motif',
        'runs' => 'Rondes',
        'punctuality' => 'Ponctualité',
        'aborted' => 'interrompues',
        'allRoutes' => 'Tous les itinéraires',
        'allSites' => 'Tous les sites',
        'empty' => 'Aucune ronde sur la période.',
        'summary' => ':runs rondes, dont :completed terminées et :aborted interrompues',
    ],
    'scan' => [
        'implausibleTime' => 'L’heure de scan de l’appareil se situe hors de la ronde — vérifiez l’horloge de l’appareil.',
    ],
    // Gestion et écarts (MVP-1611).
    'route' => [
        'edit_title' => 'Modifier l\'itinéraire',
        'responsible' => 'Responsable des écarts',
        'responsible_hint' => 'Reçoit le point ouvert en cas d\'écart. Vide : le responsable d\'équipe de la personne qui effectue la ronde.',
        'updated' => 'Itinéraire enregistré.',
        'deactivate_confirm' => 'Désactiver l\'itinéraire ? Une ronde en cours peut se terminer ; aucune nouvelle ne peut être lancée.',
        'deactivated' => 'Itinéraire désactivé — il ne peut plus être lancé.',
        'activated' => 'L\'itinéraire est de nouveau actif.',
    ],
    'checkpoint' => [
        'edit_title' => 'Modifier le point de contrôle',
        'edit_hint' => 'Les modifications valent pour les scans futurs ; les scans enregistrés conservent leur évaluation.',
        'updated' => 'Point de contrôle « :label » enregistré.',
        'deactivate_confirm' => 'Désactiver le point de contrôle ? Il n\'est plus attendu à partir de maintenant et ne peut plus être scanné ; les rondes antérieures restent inchangées.',
        'deactivated_done' => 'Point de contrôle « :label » désactivé.',
        'deactivated' => 'Ce point de contrôle est désactivé.',
        'inactive_badge' => 'désactivé',
    ],
    'deviation' => [
        'running_title' => 'La ronde « :route » présente un écart',
        'late_line' => '« :label » hors de la fenêtre (:delta min)',
        'missed_line' => ':count points de contrôle échus sans scan',
        'overdue_title' => 'Ronde « :route » non terminée',
        'overdue_line' => 'Durée prévue de :minutes minutes dépassée (début :start).',
    ],
];
