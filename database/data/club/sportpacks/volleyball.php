<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : volleyball.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'volleyball',
    'label' => 'Volleyball',
    'profile' => ['name' => 'Volleyball', 'family' => 'team_ball', 'result_format' => 'sets', 'squad_size_field' => 6, 'squad_size_bench' => 6, 'positions' => "zu=Zuspiel\nda=Diagonal\naa=Außenangriff\nmb=Mittelblock\nli=Libero", 'age_cutoff' => '01-01', 'resource_types' => 'Sporthalle, Beachfeld', 'disciplines' => ''],
    'department' => ['name' => 'Volleyball', 'discipline' => 'Volleyball'],
    'groups' => [
        ['name' => 'Volleyball Mixed Freizeit', 'min_age' => 16],
        ['name' => 'Volleyball Damen', 'is_team' => true, 'age_class' => 'Damen'],
    ],
    'resources' => [
        ['name' => 'Beachfeld', 'kind' => 'court', 'capacity' => 1],
    ],
];
