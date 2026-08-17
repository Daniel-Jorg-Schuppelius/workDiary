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
        'change_order_status' => [
            'Recog' => 'erkannt',
            'Filed' => 'angemeldet',
            'Offered' => 'angeboten',
            'Withdrawn' => 'zurückgezogen',
            'Rejected' => 'abgelehnt',
            'ObjToRecj' => 'Widerspruch zur Ablehnung',
            'FormAckn' => 'sachlich anerkannt',
            'Approved' => 'genehmigt',
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
        '31' => 'Mengenermittlung',
        '50' => 'Baukostenkatalog',
        '51' => 'Kostenermittlung',
        '52' => 'Kalkulationsdaten',
        '80' => 'Universelle LV-Daten',
        '81' => 'Leistungsverzeichnis',
        '82' => 'Kostenansatz',
        '83' => 'Angebotsaufforderung',
        '84' => 'Angebotsabgabe',
        '85' => 'Nebenangebot',
        '86' => 'Auftragserteilung',
        '87' => 'Auftragsbestätigung',
        '89' => 'Rechnung',
        '89B' => 'Rechnungsbegründende Unterlage',
        '83Z' => 'Zeitvertrag: Angebotsaufforderung',
        '84Z' => 'Zeitvertrag: Angebotsabgabe',
        '86ZE' => 'Zeitvertrag: Einzelauftrag',
        '86ZR' => 'Zeitvertrag: Rahmenauftrag',
        '93' => 'Preisanfrage',
        '94' => 'Preisangebot',
        '96' => 'Bestellung',
        '97' => 'Auftragsbestätigung (Handel)',
    ],

    'item' => [
        'type' => [
            'standard' => 'Normalposition',
            'base' => 'Grundposition',
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
        'unpriced_item' => 'Position :ref ist im Angebot weder bepreist noch als „nicht angeboten“ gekennzeichnet.',
        'priced_but_not_offered' => 'Position :ref ist als „nicht angeboten“ gekennzeichnet, trägt aber einen Einheitspreis.',
        'up_components_mismatch' => 'Position :ref: Summe der Einheitspreisanteile (:sum) weicht vom Einheitspreis (:price) ab.',
        'missing_text' => 'Position :ref ohne Kurz-/Langtext.',
        'total_mismatch' => 'Die angegebene Summe (:stated) weicht von der nachgerechneten Summe (:computed) ab.',
        'complement_empty' => 'Position :ref: Bieter-Textergänzung :mark ist nicht ausgefüllt.',
        'contractor_missing' => 'Für diese Phase fehlt die Anschrift des Bieters (Name, Straße, PLZ und Ort in den E-Rechnungs-Stammdaten).',
    ],

    'flash' => [
        'imported' => 'Leistungsverzeichnis mit :items Positionen importiert.',
        'preflight_failed' => 'Import abgebrochen: :count Preflight-Fehler. Es wurden keine Positionen geschrieben.',
        'conflict' => 'Reimport abgebrochen: Positionen mit Ausführungsbezug (:refs) würden überschrieben.',
    ],

    'progress' => [
        'from_takeoff' => 'Menge aus :lines Aufmaßzeilen der X31 nachgerechnet.',
        'takeoff_skipped' => ':count Zeilen mit nicht unterstützter Formel blieben unberücksichtigt.',
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
