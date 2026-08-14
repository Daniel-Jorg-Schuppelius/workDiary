<?php
/*
 * Created on   : Fri Jun 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : work_schedule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'type' => [
        'flextime' => 'Horaire flexible',
        'weekly' => 'Durée hebdomadaire fixe',
        'per_weekday' => 'Par jour de la semaine',
        'trust' => 'Temps de travail en confiance',
    ],
    'type_hint' => [
        'flextime' => 'Cible journalière uniforme les jours travaillés, avec plages fixes et variables.',
        'weekly' => 'Une seule cible hebdomadaire, librement répartissable sur la semaine.',
        'per_weekday' => 'Heures individuelles ou horaires fixes de début–fin par jour.',
        'trust' => 'Aucun suivi des cibles – seule la présence réelle est enregistrée.',
    ],
];
