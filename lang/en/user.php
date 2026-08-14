<?php
/*
 * Created on   : Wed May 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : user.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'role' => [
        'admin' => 'Administrator',
        'meldestelle' => 'Reporting Office',
        'datenschutz' => 'Data Protection',
        'geschaeftsfuehrung' => 'Management',
        'personalverwaltung' => 'Personnel Administration',
        'teamleitung' => 'Team Lead',
        'buchhaltung' => 'Accounting',
        'user' => 'Employee',
        'aussendienst' => 'Field Service',
        'callcenter' => 'Call Center',
        'support' => 'Support',
        'training_manager' => 'Training Manager',
        'kunde' => 'Customer',
    ],
    'employment_type' => [
        'vollzeit' => 'Full-time',
        'teilzeit' => 'Part-time',
        'minijob' => 'Mini-job (marginal)',
        'midijob' => 'Midi-job (transition zone)',
        'kurzfristig' => 'Short-term employment',
        'werkstudent' => 'Working student',
        'azubi' => 'Apprentice',
    ],
    'compensation_model' => [
        'payroll' => 'Internal (payroll)',
        'pauschal' => 'Flat rate',
        'nach_zeitaufwand' => 'By time spent',
    ],
    'flat_interval' => [
        'monatlich' => 'Monthly',
        'pro_einsatz' => 'Per assignment',
        'einmalig' => 'One-time',
    ],
];
