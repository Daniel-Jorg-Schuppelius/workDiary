<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : survey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

// Umfragen (Feature 090, Phase 156).
return [
    'trend' => [
        'title' => 'NPS par mois',
        'unit' => 'NPS',
    ],
    'triggers' => [
        'title' => 'Déclencheurs',
        'order' => 'Inviter automatiquement après la clôture d’une intervention',
        'course' => 'Inviter automatiquement après la fin d’un cours',
        'schedule' => 'Inviter à intervalle fixe (jours)',
        'scheduleHint' => 'Clients ayant une intervention terminée ou un ticket résolu dans l’intervalle, au plus une fois par intervalle. Vide = désactivé.',
        'saved' => 'Déclencheurs enregistrés.',
    ],
];
