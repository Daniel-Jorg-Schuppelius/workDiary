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
    'employees' => 'Employees',

    // Billing and cost rate per person (MVP-1565), visible only with user.payroll.manage.
    'rates' => [
        'legend' => 'Rates for billing and costing',
        'hint' => 'Confidential: visible and editable only with the permission “Manage personnel & payroll data”. Changes affect times recorded or recalculated afterwards and are logged.',
        'hourly_rate' => 'Hourly rate (billing)',
        'hourly_rate_hint' => 'Takes precedence over the rate of task, project and customer; only special conditions and a rate on the time entry rank higher. Leave empty if the project or customer rate should apply.',
        'internal_rate' => 'Internal cost rate',
        'internal_rate_hint' => 'Cost per hour of this person for costing; takes precedence over the cost rate of task, project and customer.',
    ],

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
