<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : tennis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'tennis',
    'label' => 'Tennis',
    'profile' => ['name' => 'Tennis', 'family' => 'racket', 'result_format' => 'sets', 'has_doubles' => true, 'positions' => '', 'age_cutoff' => '01-01', 'resource_types' => 'Platz', 'disciplines' => ''],
    'department' => ['name' => 'Tennis', 'discipline' => 'Tennis'],
    'groups' => [
        ['name' => 'Tennis Jugend', 'min_age' => 6, 'max_age' => 17],
        ['name' => 'Tennis Herren 40', 'min_age' => 40, 'is_team' => true, 'age_class' => 'Herren 40'],
        ['name' => 'Tennis Damen', 'is_team' => true, 'age_class' => 'Damen'],
    ],
    'resources' => [
        ['name' => 'Tennisplatz 1', 'kind' => 'court', 'capacity' => 1],
        ['name' => 'Tennisplatz 2', 'kind' => 'court', 'capacity' => 1],
        ['name' => 'Tennisplatz 3', 'kind' => 'court', 'capacity' => 1],
    ],
];
