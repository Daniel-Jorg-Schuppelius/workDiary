<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : basketball.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'basketball',
    'label' => 'Basketball',
    'profile' => ['name' => 'Basketball', 'family' => 'team_ball', 'result_format' => 'period_points', 'squad_size_field' => 5, 'squad_size_bench' => 7, 'positions' => "pg=Point Guard\nsg=Shooting Guard\nsf=Small Forward\npf=Power Forward\nc=Center", 'age_cutoff' => '01-01', 'resource_types' => 'Sporthalle', 'disciplines' => ''],
    'department' => ['name' => 'Basketball', 'discipline' => 'Basketball'],
    'groups' => [
        ['name' => 'Basketball U14', 'min_age' => 12, 'max_age' => 13, 'is_team' => true, 'age_class' => 'U14'],
        ['name' => 'Basketball U18', 'min_age' => 16, 'max_age' => 17, 'is_team' => true, 'age_class' => 'U18'],
        ['name' => 'Basketball Herren', 'is_team' => true, 'age_class' => 'Herren'],
    ],
    'resources' => [],
];
