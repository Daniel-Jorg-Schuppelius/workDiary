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
    // Mehrzahl für Menü, Seitentitel und Zählkacheln; Spalten und Felder nutzen „Mitarbeiter“ (Einzahl).
    'employees' => 'Mitarbeiter',

    // Verrechnungs- und Kostensatz je Person (MVP-1565), sichtbar nur mit user.payroll.manage.
    'rates' => [
        'legend' => 'Sätze für Abrechnung und Nachkalkulation',
        'hint' => 'Vertraulich: sichtbar und änderbar nur mit dem Recht „Personal-/Lohndaten verwalten“. Änderungen wirken auf Zeiten, die danach erfasst oder neu berechnet werden, und werden protokolliert.',
        'hourly_rate' => 'Stundensatz (Verrechnung)',
        'hourly_rate_hint' => 'Geht dem Satz von Aufgabe, Projekt und Kunde vor; nur Sonderkonditionen und ein Satz am Zeiteintrag haben Vorrang. Leer lassen, wenn der Projekt- oder Kundensatz gelten soll.',
        'internal_rate' => 'Interner Kostensatz',
        'internal_rate_hint' => 'Kosten je Stunde dieser Person für die Nachkalkulation; geht dem Kostensatz von Aufgabe, Projekt und Kunde vor.',
    ],

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
