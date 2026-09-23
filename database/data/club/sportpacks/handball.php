<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : handball.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'handball',
    'label' => 'Handball',
    'profile' => ['name' => 'Handball', 'family' => 'team_ball', 'result_format' => 'goals', 'squad_size_field' => 7, 'squad_size_bench' => 7, 'positions' => "tw=Torwart\nla=Linksaußen\nrl=Rückraum links\nrm=Rückraum Mitte\nrr=Rückraum rechts\nra=Rechtsaußen\nkm=Kreisläufer", 'age_cutoff' => '01-01', 'resource_types' => 'Sporthalle', 'disciplines' => ''],
    'department' => ['name' => 'Handball', 'discipline' => 'Handball'],
    'groups' => [
        ['name' => 'Handball D-Jugend', 'min_age' => 10, 'max_age' => 12, 'is_team' => true, 'age_class' => 'D-Jugend'],
        ['name' => 'Handball Damen', 'is_team' => true, 'age_class' => 'Damen'],
        ['name' => 'Handball Herren', 'is_team' => true, 'age_class' => 'Herren'],
    ],
    'resources' => [
        ['name' => 'Sporthalle', 'kind' => 'hall', 'capacity' => 1, 'children' => [['name' => 'Sporthalle Drittel 1', 'kind' => 'part'], ['name' => 'Sporthalle Drittel 2', 'kind' => 'part'], ['name' => 'Sporthalle Drittel 3', 'kind' => 'part']]],
    ],
];
