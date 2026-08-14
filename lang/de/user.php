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
        'meldestelle' => 'Meldestelle',
        'datenschutz' => 'Datenschutz',
        'geschaeftsfuehrung' => 'Geschäftsführung',
        'personalverwaltung' => 'Personalverwaltung',
        'teamleitung' => 'Teamleitung',
        'buchhaltung' => 'Buchhaltung',
        'user' => 'Mitarbeiter',
        'aussendienst' => 'Außendienst',
        'callcenter' => 'Callcenter',
        'support' => 'Support',
        'training_manager' => 'Schulungsverantwortliche/r',
        'kunde' => 'Kunde',
    ],
    'employment_type' => [
        'vollzeit' => 'Vollzeit',
        'teilzeit' => 'Teilzeit',
        'minijob' => 'Minijob (geringfügig)',
        'midijob' => 'Midijob (Übergangsbereich)',
        'kurzfristig' => 'Kurzfristige Beschäftigung',
        'werkstudent' => 'Werkstudent/in',
        'azubi' => 'Auszubildende/r',
    ],
    'compensation_model' => [
        'payroll' => 'Intern (Lohnabrechnung)',
        'pauschal' => 'Pauschal',
        'nach_zeitaufwand' => 'Nach Zeitaufwand',
    ],
    'flat_interval' => [
        'monatlich' => 'Monatlich',
        'pro_einsatz' => 'Pro Einsatz',
        'einmalig' => 'Einmalig',
    ],
];
