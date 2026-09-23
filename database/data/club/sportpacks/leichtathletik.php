<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : leichtathletik.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Startpaket je Sportart (Feature 159, MVP-848): Sportartenprofil, Abteilung,
// Gruppen, Ressourcen und je nach Familie Graduierung, Pferde oder
// Nachweisanforderung — vom Verein anpassbar, keine Verbandsregeln.
return [
    'code' => 'leichtathletik',
    'label' => 'Leichtathletik',
    'profile' => ['name' => 'Leichtathletik', 'family' => 'individual', 'result_format' => 'none', 'positions' => '', 'age_cutoff' => '01-01', 'resource_types' => 'Laufbahn, Sprunggrube, Wurfanlage', 'disciplines' => "100m=100 m Lauf;s;ja\n800m=800 m Lauf;s;ja\nweit=Weitsprung;m;nein\nhoch=Hochsprung;m;nein\nkugel=Kugelstoßen;m;nein"],
    'department' => ['name' => 'Leichtathletik', 'discipline' => 'Leichtathletik'],
    'groups' => [
        ['name' => 'LA Kinder (6–11)', 'min_age' => 6, 'max_age' => 11],
        ['name' => 'LA Jugend (12–17)', 'min_age' => 12, 'max_age' => 17],
        ['name' => 'LA Aktive', 'min_age' => 18],
    ],
    'resources' => [
        ['name' => 'Laufbahn', 'kind' => 'lane', 'capacity' => 6],
        ['name' => 'Sprunggrube', 'kind' => 'other', 'capacity' => 1],
    ],
];
