<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kampfsport.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'kampfsport',
    'label' => 'Kampfsport (Judo / Karate)',
    'profile' => ['name' => 'Kampfsport', 'family' => 'martial_arts', 'result_format' => 'none', 'positions' => '', 'age_cutoff' => null, 'resource_types' => 'Matte, Dojo', 'disciplines' => ''],
    'department' => ['name' => 'Kampfsport', 'discipline' => 'Judo'],
    'groups' => [
        ['name' => 'Judo Kinder (6–9)', 'min_age' => 6, 'max_age' => 9, 'discipline' => 'Judo'],
        ['name' => 'Judo Jugend (10–15)', 'min_age' => 10, 'max_age' => 15, 'discipline' => 'Judo'],
        ['name' => 'Judo Erwachsene', 'min_age' => 16, 'discipline' => 'Judo'],
    ],
    'resources' => [
        ['name' => 'Dojo', 'kind' => 'hall', 'capacity' => 1, 'children' => [['name' => 'Matte A', 'kind' => 'part'], ['name' => 'Matte B', 'kind' => 'part']]],
    ],
    'grading' => [
        'name' => 'Judo Kyu-Grade', 'discipline' => 'Judo',
        'grades' => ['8. Kyu (weiß-gelb)', '7. Kyu (gelb)', '6. Kyu (gelb-orange)', '5. Kyu (orange)', '4. Kyu (orange-grün)', '3. Kyu (grün)', '2. Kyu (blau)', '1. Kyu (braun)'],
        'requirements' => ['min_minutes' => 1200, 'wait_months' => 3, 'min_age' => 6],
    ],
];
