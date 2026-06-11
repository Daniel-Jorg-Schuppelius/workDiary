<?php
/*
 * Created on   : Wed Jul 01 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : exports.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Services\TimeExport\Profiles\{DatevLodasProfile, GenericCsvProfile};

/**
 * Konfiguration des ApprovedTimeExporters (MVP-019).
 *
 *  - default: vorausgewähltes Profil
 *  - profiles[*].driver:   FQCN, das ExportProfile implementiert
 *  - profiles[*].format:   Datei-Endung (csv|txt|xml)
 *  - profiles[*].options:  profil-spezifische Schalter
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
            'options' => [
                // Lohnart fuer Normalstunden ohne eigenen wage_type_code.
                'normal_wage_type_code' => '1000',
            ],
        ],
        'lexware' => [
            'driver' => null,
            'label' => 'Lexware Lohn (vorbereitet)',
            'format' => 'csv',
            'options' => [],
        ],
    ],

    'storage' => [
        'disk' => env('TIME_EXPORT_DISK', 'local'),
        'path_pattern' => 'exports/{org}/{year}-{month}/{profile}-{hash}.{ext}',
    ],

    'retention_years' => 10,
];
