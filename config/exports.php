<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : exports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Services\TimeExport\Profiles\{DatevLodasProfile, GenericCsvProfile, LexwareProfile};

/**
 * Konfiguration des ApprovedTimeExporters (MVP-019).
 *
 *  - default: vorausgewähltes Profil
 *  - profiles[*].driver:   FQCN, das ExportProfile implementiert
 *  - profiles[*].format:   Datei-Endung (csv|txt|xml)
 *  - profiles[*].options:  profil-spezifische Schalter
 *  - profiles[*].requires_wage_type_codes: Preflight (A21) — jede Zeile außer
 *    work.normal braucht eine auflösbare externe Lohnart (Org-Mapping oder
 *    Zuschlagsregel-Code), sonst bricht der Export mit Fehlermeldung ab
 *  - profiles[*].wage_type_code_pattern: Validierungs-Regex der externen
 *    Lohnartennummer im Mapping-UI (Format des Zielsystems)
 *  - storage.disk:         Filesystem-Disk für die Export-Dateien
 *  - storage.path_pattern: relativer Pfad-Bauplan
 *  - retention_years:      gesetzlich vorgehaltene Aufbewahrungsdauer
 */
return [
    'default' => 'generic',

    'profiles' => [
        'generic' => [
            'driver' => GenericCsvProfile::class,
            'label' => 'Allgemein (CSV, UTF-8, ;)',
            'format' => 'csv',
            // Generisch: Datei führt interne wage_type-Schlüssel, keine
            // Zielsystem-Nummern — Mapping/Preflight greifen hier nicht.
            'requires_wage_type_codes' => false,
            'wage_type_code_pattern' => '/^[A-Za-z0-9][A-Za-z0-9._-]{0,19}$/',
            'options' => [
                'delimiter' => ';',
                'enclosure' => '"',
                'eol' => "\r\n",
                'bom' => true,
            ],
        ],
        // DATEV-LODAS-naher CSV-Aufbau (Feature 005): Personalnummer;Datum;
        // Lohnart;Stunden — siehe DatevLodasProfile-Docblock.
        'datev' => [
            'driver' => DatevLodasProfile::class,
            'label' => 'DATEV LODAS (CSV: PersNr;Datum;Lohnart;Stunden)',
            'format' => 'csv',
            // LODAS-Lohnarten sind numerisch (max. 4 Stellen).
            'requires_wage_type_codes' => true,
            'wage_type_code_pattern' => '/^[0-9]{1,4}$/',
            'options' => [
                // Lohnart fuer Normalstunden ohne eigenen wage_type_code.
                'normal_wage_type_code' => '1000',
            ],
        ],
        'lexware' => [
            'driver' => LexwareProfile::class,
            'label' => 'Lexware Lohn (CSV: Jahr;Monat;PersNr;Lohnart;Wert;Satz, ANSI)',
            'format' => 'csv',
            // Lexware-Lohnartnummern sind numerisch (max. 4 Stellen).
            'requires_wage_type_codes' => true,
            'wage_type_code_pattern' => '/^[0-9]{1,4}$/',
            'options' => [
                // Default-Lohnart für Normalstunden (Zeilen ohne eigene wage_type_code).
                'normal_wage_type_code' => '1000',
            ],
        ],
    ],

    'storage' => [
        'disk' => env('TIME_EXPORT_DISK', 'local'),
        'path_pattern' => 'exports/{org}/{year}-{month}/{profile}-{hash}.{ext}',
    ],

    'retention_years' => 10,
];
