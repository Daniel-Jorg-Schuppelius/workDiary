<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fussball.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'fussball',
    'label' => 'Fußball',
    'profile' => ['name' => 'Fußball', 'family' => 'team_ball', 'result_format' => 'goals', 'squad_size_field' => 11, 'squad_size_bench' => 7, 'positions' => "tw=Torwart\nab=Abwehr\nmf=Mittelfeld\nst=Sturm", 'age_cutoff' => '01-01', 'resource_types' => 'Rasenplatz, Kunstrasen', 'disciplines' => ''],
    'department' => ['name' => 'Fußball', 'discipline' => 'Fußball'],
    'groups' => [
        ['name' => 'Fußball E-Jugend', 'min_age' => 8, 'max_age' => 10, 'is_team' => true, 'age_class' => 'U11'],
        ['name' => 'Fußball C-Jugend', 'min_age' => 12, 'max_age' => 14, 'is_team' => true, 'age_class' => 'U15'],
        ['name' => 'Fußball Erste Herren', 'is_team' => true, 'age_class' => 'Herren'],
    ],
    'resources' => [
        ['name' => 'Rasenplatz 1', 'kind' => 'pitch', 'capacity' => 1, 'children' => [['name' => 'Rasenplatz 1 Hälfte A', 'kind' => 'part'], ['name' => 'Rasenplatz 1 Hälfte B', 'kind' => 'part']]],
        ['name' => 'Kunstrasenplatz', 'kind' => 'pitch', 'capacity' => 1],
    ],
];
