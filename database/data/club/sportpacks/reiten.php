<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : reiten.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'reiten',
    'label' => 'Reiten',
    'profile' => ['name' => 'Reiten', 'family' => 'equestrian', 'result_format' => 'none', 'positions' => '', 'age_cutoff' => null, 'resource_types' => 'Reithalle, Reitplatz, Pferd', 'disciplines' => ''],
    'department' => ['name' => 'Reiten', 'discipline' => 'Reiten'],
    'groups' => [
        ['name' => 'Reitgruppe Anfänger', 'min_age' => 6],
        ['name' => 'Reitgruppe Fortgeschrittene', 'min_age' => 8],
        ['name' => 'Voltigieren', 'min_age' => 5, 'max_age' => 14],
    ],
    'resources' => [
        ['name' => 'Reithalle', 'kind' => 'hall', 'capacity' => 1, 'children' => [['name' => 'Reithalle Hälfte A', 'kind' => 'part'], ['name' => 'Reithalle Hälfte B', 'kind' => 'part']]],
        ['name' => 'Außenreitplatz', 'kind' => 'pitch', 'capacity' => 1],
    ],
    'horses' => [
        ['name' => 'Fanny', 'kind' => 'school', 'suitable_for' => 'Anfänger, Voltigieren', 'max_uses_per_day' => 3, 'rest_minutes' => 30],
        ['name' => 'Max', 'kind' => 'school', 'suitable_for' => 'Fortgeschrittene', 'max_uses_per_day' => 2, 'rest_minutes' => 30],
    ],
];
