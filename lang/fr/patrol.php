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
];
