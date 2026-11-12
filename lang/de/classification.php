<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : classification.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'dataquality' => [
        'heading' => 'Datenqualität: Pflichtklassifikationen fehlen',
        'missing' => ':domain fehlt',
    ],
    'error' => [
        'requirementUnmet' => 'Pflichtklassifikationen fehlen: :domains',
        'requirementMin' => 'Pflichtklassifikation „:domain“: mindestens :min Angabe(n) nötig, vorhanden: :actual.',
        'requirementMax' => 'Pflichtklassifikation „:domain“: höchstens :max Angabe(n) erlaubt, vorhanden: :actual.',
        'referenced' => 'Der Wert „:label“ wird noch verwendet (:usage) und kann nicht gelöscht werden. Legen Sie ihn stattdessen still: Er verschwindet aus neuen Auswahlen, bestehende Datensätze behalten ihn.',
    ],
    'action' => [
        'deactivate' => 'Stilllegen',
        'deactivate_confirm' => 'Wert stilllegen? Er ist danach nicht mehr neu wählbar, bestehende Datensätze behalten ihn.',
    ],
    'flash' => [
        'deactivated' => '„:label“ wurde stillgelegt.',
    ],
    'reference' => [
        'time_entries' => 'Zeiteinträge',
        'claim_cases' => 'Reklamationen',
        'products' => 'Artikel',
        'classifiables' => 'Zuordnungen an Datensätzen',
        'import_value_mappings' => 'Import-Zuordnungen',
        'assets' => 'Assets',
    ],
];
