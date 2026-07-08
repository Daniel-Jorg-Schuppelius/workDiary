<?php
/*
 * Created on   : Fri Jul 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ideas.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => [
        'index' => 'Ideenlandkarten',
    ],
    'subtitle' => 'Private und gemeinsame Ideenlandkarten — sichtbar nur für Eigentümer und ausdrücklich Freigegebene.',
    'empty' => 'Noch keine Ideenlandkarten.',
    'privacy_hint' => 'Neue Karten sind privat: sichtbar nur für Sie, bis Sie sie ausdrücklich für Personen oder Teams freigeben.',
    'confirm_delete' => 'Karte in den Papierkorb verschieben?',

    'action' => [
        'create' => 'Karte anlegen',
        'edit' => 'Karte bearbeiten',
        'archive' => 'Archivieren',
        'unarchive' => 'Reaktivieren',
        'restore' => 'Wiederherstellen',
    ],

    'col' => [
        'title' => 'Titel',
        'description' => 'Beschreibung',
        'owner' => 'Eigentümer',
        'visibility' => 'Sichtbarkeit',
        'nodes' => 'Knoten',
        'updated' => 'Geändert',
        'actions' => 'Aktionen',
    ],

    'filter' => [
        'active' => 'Aktiv',
        'archived' => 'Archiviert',
        'trashed' => 'Papierkorb',
    ],

    'visibility' => [
        'private' => 'Privat',
        'shared' => 'Geteilt',
    ],

    'share_role' => [
        'viewer' => 'Lesen',
        'editor' => 'Bearbeiten',
    ],

    'color' => [
        'default' => 'Neutral',
        'primary' => 'Blau',
        'success' => 'Grün',
        'warning' => 'Gelb',
        'error' => 'Rot',
        'info' => 'Türkis',
    ],

    'node_status' => [
        'open' => 'Offen',
        'in_review' => 'In Prüfung',
        'decided' => 'Beschlossen',
        'rejected' => 'Verworfen',
        'done' => 'Umgesetzt',
    ],

    'import' => [
        'action' => 'Importieren',
        'title' => 'Ideenlandkarte importieren',
        'submit' => 'Importieren',
        'file' => 'Datei',
        'hint' => 'FreeMind/Freeplane (.mm) oder OPML. Erzeugt eine neue, private Karte.',
        'done' => 'Karte importiert.',
        'default_title' => 'Importierte Karte',
        'error' => [
            'invalid' => 'Die Datei ist kein gültiges XML.',
            'unsupported' => 'Nicht unterstütztes Format (nur FreeMind .mm und OPML).',
            'empty' => 'Die Datei enthält keine Knoten.',
            'too_deep' => 'Die Struktur ist zu tief verschachtelt.',
            'too_large' => 'Die Karte hat zu viele Knoten.',
        ],
    ],

    'legend' => [
        'context' => 'Kontext (optional)',
        'map' => 'Karte',
    ],

    'outline' => [
        'title' => 'Gliederung',
        'empty' => 'Diese Karte hat noch keine Knoten.',
    ],

    'flash' => [
        'created' => 'Karte angelegt.',
        'updated' => 'Karte gespeichert.',
        'archived' => 'Karte archiviert.',
        'unarchived' => 'Karte reaktiviert.',
        'deleted' => 'Karte in den Papierkorb verschoben.',
        'restored' => 'Karte wiederhergestellt.',
        'owner_invalid' => 'Ungültiger neuer Eigentümer.',
        'ownership_transferred' => 'Eigentum übertragen.',
        'share_granted' => 'Freigabe erteilt.',
        'share_revoked' => 'Freigabe entzogen.',
        'share_invalid' => 'Ungültige Freigabe-Auswahl (genau eine Person oder ein Team).',
    ],

    'share' => [
        'title' => 'Freigaben',
        'none' => 'Diese Karte ist privat — keine Freigaben.',
        'user' => 'Person',
        'team' => 'Team',
        'role' => 'Rolle',
        'add' => 'Freigeben',
        'revoke' => 'Freigabe entziehen',
        'hint' => 'Genau eine Person ODER ein Team je Freigabe. Teammitgliedschaft wird beim Zugriff geprüft.',
    ],

    'notification' => [
        'shared' => ':actor hat eine Ideenlandkarte für Sie freigegeben.',
    ],

    'export' => [
        'generated_at' => 'Erstellt am',
        'footer_note' => 'Export der Gliederungsdarstellung — Positionen der Canvas-Ansicht sind im JSON-Export enthalten.',
    ],

    'context' => [
        'customer' => 'Kunde',
        'project' => 'Projekt',
    ],

    'convert' => [
        'done' => 'Überführt:',
        'already' => 'Bereits überführt:',
        'error' => [
            'module_disabled' => 'Das Zielmodul ist nicht aktiviert.',
            'target_not_allowed' => 'Dieses Ziel ist nicht zulässig.',
        ],
    ],

    'editor' => [
        'outline' => 'Gliederung',
        'canvas' => 'Karte',
        'saving' => 'Speichert …',
        'undo_delete' => 'Löschen rückgängig',
        'keys_hint' => 'Enter: neuer Knoten · Tab: einrücken · Alt+↑/↓: verschieben · F2: umbenennen',
        'conflict_title' => 'Gleichzeitige Änderung erkannt — Ihr Stand war veraltet.',
        'conflict_take_server' => 'Server-Stand übernehmen',
        'conflict_retry_mine' => 'Meine Änderung erneut anwenden',
        'new_node' => 'Neue Idee',
        'convert_task' => 'Als Aufgabe',
        'convert_project' => 'Als Projekt',
        'convert_knowledge' => 'Als Wissensartikel',
        'confirm_delete_node' => 'Knoten samt Unterknoten in den Papierkorb verschieben?',
        'add_child' => 'Unterknoten anlegen',
        'rename' => 'Umbenennen',
        'details' => 'Details',
        'move_up' => 'Nach oben',
        'move_down' => 'Nach unten',
        'indent' => 'Einrücken',
        'outdent' => 'Ausrücken',
        'delete' => 'Löschen',
        'expand' => 'Zweig aufklappen',
        'collapse' => 'Zweig zuklappen',
        'zoom_in' => 'Vergrößern',
        'zoom_out' => 'Verkleinern',
        'zoom_reset' => 'Zoom auf 100 %',
        'fit' => 'Ansicht einpassen',
        'arrange' => 'Anordnen',
        'arrange_hint' => 'Alle Knoten automatisch als Baum anordnen',
        'canvas_large' => 'Große Arbeitsfläche',
        'canvas_small' => 'Kompakte Arbeitsfläche',
        'canvas_keys_hint' => 'Tab: Unterknoten · Enter: Geschwister · Doppelklick auf Fläche: neue Idee · Knoten auf Knoten ziehen: umhängen',
        'canvas_a11y_hint' => 'Barrierefreie Bearbeitung in der Gliederung.',
        'export_svg' => 'Als SVG-Bild exportieren',
        'export_png' => 'Als PNG-Bild exportieren',
        'history' => 'Verlauf',
        'history_empty' => 'Noch keine Änderungen.',
        'presence_suffix' => 'bearbeitet gerade',
        'note' => 'Notiz',
        'color' => 'Farbe',
        'status' => 'Status',
        'status_none' => '— kein Status',
    ],

    'error' => [
        'conflict' => 'Der Knoten wurde zwischenzeitlich geändert — bitte Stand prüfen.',
        'cycle' => 'Ein Knoten kann nicht unter einen eigenen Unterknoten verschoben werden.',
        'root_immovable' => 'Der Wurzelknoten kann nicht verschoben oder gelöscht werden.',
        'foreign_node' => 'Der Knoten gehört nicht zu dieser Karte.',
    ],
];
