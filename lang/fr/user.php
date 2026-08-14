<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : user.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'role' => [
        'admin' => 'Administrateur',
        'meldestelle' => 'Point de signalement',
        'datenschutz' => 'Protection des données',
        'geschaeftsfuehrung' => 'Direction',
        'personalverwaltung' => 'Gestion du personnel',
        'teamleitung' => 'Chef d\'équipe',
        'buchhaltung' => 'Comptabilité',
        'user' => 'Employé',
        'aussendienst' => 'Service externe',
        'callcenter' => 'Centre d\'appels',
        'support' => 'Support',
        'training_manager' => 'Responsable de formation',
        'kunde' => 'Client',
    ],
    'employment_type' => [
        'vollzeit' => 'Temps plein',
        'teilzeit' => 'Temps partiel',
        'minijob' => 'Mini-job (marginal)',
        'midijob' => 'Midi-job (zone de transition)',
        'kurzfristig' => 'Emploi de courte durée',
        'werkstudent' => 'Étudiant salarié',
        'azubi' => 'Apprenti',
    ],
    'compensation_model' => [
        'payroll' => 'Interne (paie)',
        'pauschal' => 'Forfait',
        'nach_zeitaufwand' => 'Au temps passé',
    ],
    'flat_interval' => [
        'monatlich' => 'Mensuel',
        'pro_einsatz' => 'Par intervention',
        'einmalig' => 'Unique',
    ],
];
