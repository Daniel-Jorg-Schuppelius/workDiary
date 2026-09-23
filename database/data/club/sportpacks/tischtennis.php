<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : tischtennis.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'tischtennis',
    'label' => 'Tischtennis',
    'profile' => ['name' => 'Tischtennis', 'family' => 'racket', 'result_format' => 'sets', 'has_doubles' => true, 'positions' => '', 'age_cutoff' => '01-01', 'resource_types' => 'Tisch', 'disciplines' => ''],
    'department' => ['name' => 'Tischtennis', 'discipline' => 'Tischtennis'],
    'groups' => [
        ['name' => 'TT Jugend', 'min_age' => 8, 'max_age' => 17],
        ['name' => 'TT Herren I', 'is_team' => true, 'age_class' => 'Herren'],
        ['name' => 'TT Damen I', 'is_team' => true, 'age_class' => 'Damen'],
    ],
    'resources' => [
        ['name' => 'Tischtennishalle', 'kind' => 'hall', 'capacity' => 1, 'children' => [['name' => 'Tisch 1', 'kind' => 'table'], ['name' => 'Tisch 2', 'kind' => 'table'], ['name' => 'Tisch 3', 'kind' => 'table'], ['name' => 'Tisch 4', 'kind' => 'table'], ['name' => 'Tisch 5', 'kind' => 'table'], ['name' => 'Tisch 6', 'kind' => 'table'], ['name' => 'Tisch 7', 'kind' => 'table'], ['name' => 'Tisch 8', 'kind' => 'table']]],
    ],
];
