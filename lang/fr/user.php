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
    'employees' => 'Employés',

    // Taux de facturation et de coût par personne (MVP-1565), visible uniquement avec user.payroll.manage.
    'rates' => [
        'legend' => 'Taux pour la facturation et le calcul a posteriori',
        'hint' => 'Confidentiel : visible et modifiable uniquement avec le droit « Gérer les données du personnel et de la paie ». Les modifications s’appliquent aux temps saisis ou recalculés par la suite et sont journalisées.',
        'hourly_rate' => 'Taux horaire (facturation)',
        'hourly_rate_hint' => 'Prime sur le taux de la tâche, du projet et du client ; seules les conditions particulières et un taux saisi sur l’entrée de temps passent avant. Laissez vide si le taux du projet ou du client doit s’appliquer.',
        'internal_rate' => 'Taux de coût interne',
        'internal_rate_hint' => 'Coût horaire de cette personne pour le calcul a posteriori ; prime sur le taux de coût de la tâche, du projet et du client.',
    ],

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
