<?php
/*
 * Created on   : Sun Jun 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gaeb.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Leistungsverzeichnisse',
    'subtitle' => 'GAEB-Leistungsverzeichnisse importieren und Positionen nachverfolgen',
    'empty' => 'Noch keine Leistungsverzeichnisse importiert.',
    'import_button' => 'GAEB-Datei importieren',

    'columns' => [
        'name' => 'Bezeichnung',
        'project' => 'Projekt',
        'phase' => 'Phase',
        'version' => 'GAEB-Version',
        'items' => 'Positionen',
        'reference_no' => 'OZ',
        'short_text' => 'Kurztext',
        'quantity' => 'Menge',
        'unit' => 'Einheit',
        'unit_price' => 'EP',
        'total_price' => 'GP',
        'type' => 'Art',
        'status' => 'Status',
        'executed' => 'Aufmaß',
        'remaining' => 'Rest',
    ],

    'import' => [
        'title' => 'GAEB-Datei importieren',
        'file' => 'GAEB-DA-XML-Datei',
        'file_hint' => 'GAEB DA XML 3.x (z. B. .x81, .x83, .x86 oder .xml).',
        'project' => 'Projekt (optional)',
        'project_none' => '— kein Projekt —',
        'name' => 'Bezeichnung (optional)',
        'name_hint' => 'Überschreibt den Projektnamen aus der Datei.',
        'submit' => 'Importieren',
        'status' => [
            'pending' => 'In Prüfung',
            'preflight_failed' => 'Preflight fehlgeschlagen',
            'imported' => 'Importiert',
            'conflict' => 'Konflikt',
        ],
    ],

    'show' => [
        'positions' => 'Positionen',
        'history' => 'Importhistorie',
        'no_imports' => 'Keine Importläufe protokolliert.',
        'imported_at' => 'Importiert am',
        'back' => 'Zurück zur Übersicht',
    ],

    'phase' => [
        '81' => 'Leistungsverzeichnis',
        '82' => 'Kostenanschlag',
        '83' => 'Angebotsaufforderung',
        '84' => 'Angebotsabgabe',
        '85' => 'Nebenangebot',
        '86' => 'Auftragserteilung',
    ],

    'item' => [
        'type' => [
            'standard' => 'Normalposition',
            'alternative' => 'Alternativposition',
            'optional' => 'Bedarfsposition',
            'lump_sum' => 'Pauschalposition',
            'markup' => 'Zuschlagsposition',
            'note' => 'Hinweis',
        ],
        'status' => [
            'draft' => 'Entwurf',
            'imported' => 'Importiert',
            'quoted' => 'Angeboten',
            'ordered' => 'Beauftragt',
            'in_progress' => 'In Arbeit',
            'completed' => 'Abgeschlossen',
            'replaced' => 'Ersetzt',
            'cancelled' => 'Storniert',
        ],
    ],

    'preflight' => [
        'version_unknown' => 'GAEB-Version konnte nicht erkannt werden.',
        'version_unsupported' => 'GAEB-Version :version wird nicht unterstützt (Ziellinie 3.3).',
        'phase_unknown' => 'Austauschphase „:code" ist unbekannt.',
        'no_items' => 'Die Datei enthält keine Positionen.',
        'item_missing_ref' => 'Position ohne Ordnungszahl: :text',
        'duplicate_ref' => 'Ordnungszahl :ref kommt mehrfach vor.',
        'missing_quantity' => 'Position :ref ohne Menge.',
        'non_positive_quantity' => 'Position :ref hat eine Menge ≤ 0.',
        'missing_unit' => 'Position :ref ohne Einheit.',
        'missing_price' => 'Position :ref ohne Einheitspreis in einer preisführenden Phase.',
        'missing_text' => 'Position :ref ohne Kurz-/Langtext.',
    ],

    'flash' => [
        'imported' => 'Leistungsverzeichnis mit :items Positionen importiert.',
        'preflight_failed' => 'Import abgebrochen: :count Preflight-Fehler. Es wurden keine Positionen geschrieben.',
        'conflict' => 'Reimport abgebrochen: Positionen mit Ausführungsbezug (:refs) würden überschrieben.',
    ],

    'progress' => [
        'title' => 'Aufmaß / Fortschritt',
        'record' => 'Aufmaß erfassen',
        'quantity' => 'Menge',
        'note' => 'Notiz',
        'source' => [
            'manual' => 'Manuell',
            'measurement' => 'Aufmaß',
            'protocol' => 'Protokoll',
            'material' => 'Materialverbrauch',
        ],
        'flash' => [
            'recorded' => 'Aufmaß erfasst.',
        ],
    ],

    'mapping' => [
        'title' => 'Verknüpfung',
        'add' => 'Verknüpfen',
        'target_type' => 'Zieltyp',
        'article' => 'Artikel',
        'material' => 'Material',
        'factor' => 'Faktor',
        'flash' => [
            'linked' => 'Position verknüpft.',
        ],
    ],

    'workflow' => [
        'status' => 'Status setzen',
        'add_addendum' => 'Nachtrag anlegen',
        'remaining_title' => 'Restleistung',
        'no_remaining' => 'Keine offene Restleistung.',
        'flash' => [
            'item_updated' => 'Positionsstatus geändert.',
            'bill_updated' => 'LV-Status geändert.',
            'addendum_added' => 'Nachtrag angelegt.',
        ],
    ],

    'costing' => [
        'title' => 'Nachkalkulation',
        'planned' => 'Soll',
        'executed' => 'Ist (Aufmaß)',
        'remaining' => 'Rest',
        'progress' => 'Fortschritt',
    ],

    'export' => [
        'button' => 'GAEB exportieren',
        'title' => 'GAEB-Export',
        'phase' => 'Phase',
    ],
];
