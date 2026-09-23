<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hockey.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'hockey',
    'label' => 'Hockey',
    'profile' => ['name' => 'Hockey', 'family' => 'team_ball', 'result_format' => 'goals', 'squad_size_field' => 11, 'squad_size_bench' => 7, 'positions' => "tw=Torwart\nab=Abwehr\nmf=Mittelfeld\nst=Sturm", 'age_cutoff' => '01-01', 'resource_types' => 'Kunstrasen, Halle', 'disciplines' => ''],
    'department' => ['name' => 'Hockey', 'discipline' => 'Hockey'],
    'groups' => [
        ['name' => 'Hockey Knaben B', 'min_age' => 10, 'max_age' => 12, 'is_team' => true, 'age_class' => 'Knaben B'],
        ['name' => 'Hockey Damen', 'is_team' => true, 'age_class' => 'Damen'],
        ['name' => 'Hockey Herren', 'is_team' => true, 'age_class' => 'Herren'],
    ],
    'resources' => [
        ['name' => 'Kunstrasenplatz Hockey', 'kind' => 'pitch', 'capacity' => 1, 'children' => [['name' => 'Kunstrasen Hälfte Nord', 'kind' => 'part'], ['name' => 'Kunstrasen Hälfte Süd', 'kind' => 'part']]],
    ],
];
