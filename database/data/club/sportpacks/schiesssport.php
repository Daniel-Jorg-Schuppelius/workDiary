<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : schiesssport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'schiesssport',
    'label' => 'Schießsport',
    'profile' => ['name' => 'Schießsport', 'family' => 'shooting', 'result_format' => 'none', 'positions' => '', 'age_cutoff' => '01-01', 'resource_types' => 'Schießstand, Bahn', 'disciplines' => "lg=Luftgewehr 10 m;Ringe;nein\nlp=Luftpistole 10 m;Ringe;nein\nbogen=Bogen 18 m;Ringe;nein"],
    'department' => ['name' => 'Schießsport', 'discipline' => 'Schießsport'],
    'groups' => [
        ['name' => 'Schützen Jugend', 'min_age' => 12, 'max_age' => 20],
        ['name' => 'Schützen Aktive', 'min_age' => 18],
        ['name' => 'Bogen', 'min_age' => 10],
    ],
    'resources' => [
        ['name' => 'Luftgewehrstand', 'kind' => 'stand', 'capacity' => 8, 'requires_clearance' => true],
        ['name' => 'Bogenhalle', 'kind' => 'hall', 'capacity' => 1],
    ],
    // Anzahl und Zeitraum setzt der Verein — keine gesetzlichen Schwellen im Code.
    'attendance_requirement' => ['name' => 'Schießnachweis (Vereinsanforderung)', 'required_count' => 12, 'period_months' => 12, 'event_kind' => 'training', 'group' => 'Schützen Aktive'],
];
