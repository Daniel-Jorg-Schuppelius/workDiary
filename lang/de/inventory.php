<?php
/*
 * Created on   : Tue Jun 16 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : inventory.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Lager',

    'mode' => [
        'local' => 'Lokal (WorkDiary führt den Bestand)',
        'external' => 'Extern (Warenwirtschaft führt)',
        'read_only' => 'Nur Lesen (extern geführt)',
    ],

    'state' => [
        'physical' => 'Physisch',
        'reserved' => 'Reserviert',
        'blocked' => 'Gesperrt',
        'quality' => 'Qualitätsprüfung',
        'damaged' => 'Beschädigt',
        'scrap' => 'Ausschuss',
    ],

    'ownership' => [
        'own' => 'Eigenbestand',
        'customer' => 'Kundenmaterial',
        'consignment' => 'Konsignation',
        'supplier' => 'Lieferantenmaterial',
        'project' => 'Projektgebunden',
    ],

    'movement' => [
        'receipt' => 'Wareneingang',
        'issue' => 'Entnahme',
        'return' => 'Rückgabe',
        'transfer_out' => 'Umlagerung (Abgang)',
        'transfer_in' => 'Umlagerung (Zugang)',
        'reserve' => 'Reservierung',
        'release_reservation' => 'Reservierung freigegeben',
        'scrap' => 'Ausschuss',
        'correction' => 'Korrektur/Inventurdifferenz',
        'finished_good_receipt' => 'Zugang Fertigerzeugnis',
    ],

    'warehouses' => 'Lagerorte',
    'stock' => 'Bestand',
    'subtitle' => [
        'warehouses' => 'Lagerorte des Mandanten verwalten.',
        'stock' => 'Verfügbarkeit und Bewegungen je Lagerort.',
    ],
    'action' => [
        'create_warehouse' => 'Lagerort anlegen',
        'edit_warehouse' => 'Lagerort bearbeiten',
        'book' => 'Bewegung buchen',
    ],
    'field' => [
        'code' => 'Kürzel',
        'default' => 'Standard',
        'available' => 'Verfügbar',
        'physical' => 'Physisch',
        'reserved' => 'Reserviert',
        'location_note' => 'Standorthinweis',
        'warehouse' => 'Lagerort',
        'variant' => 'Variante',
        'quantity' => 'Menge',
        'movement' => 'Bewegung',
        'ownership' => 'Eigentumsart',
        'allow_negative' => 'Negativen Bestand zulassen',
    ],
    'empty' => [
        'warehouses' => 'Noch keine Lagerorte angelegt.',
        'stock' => 'Keine Bewegungen in diesem Lagerort.',
        'no_selection' => 'Kein Lagerort ausgewählt.',
    ],
    'confirm' => [
        'delete_warehouse' => 'Lagerort wirklich löschen? Nur ohne Bewegungen möglich.',
    ],
    'flash' => [
        'warehouse_created' => 'Lagerort angelegt.',
        'warehouse_updated' => 'Lagerort aktualisiert.',
        'warehouse_deleted' => 'Lagerort gelöscht.',
        'warehouse_delete_blocked' => 'Lagerort kann nicht gelöscht werden: es existieren Bewegungen.',
        'movement_posted' => 'Bewegung gebucht.',
    ],
    'reservation_status' => [
        'active' => 'Aktiv',
        'fulfilled' => 'Erfüllt',
        'released' => 'Freigegeben',
        'cancelled' => 'Storniert',
    ],
    'count_status' => [
        'counting' => 'Zählung läuft',
        'review' => 'Prüfung',
        'completed' => 'Abgeschlossen',
        'cancelled' => 'Abgebrochen',
    ],
    'count_ui' => [
        'title' => 'Inventur',
        'open' => 'Inventur eröffnen',
        'save' => 'Zählung speichern',
        'apply' => 'Differenzen buchen',
        'book' => 'Soll',
        'counted' => 'Gezählt',
        'difference' => 'Differenz',
        'counted_at' => 'Zählzeitpunkt',
        'no_counts' => 'Noch keine Inventuren für diesen Lagerort.',
        'no_selection' => 'Kein Lagerort ausgewählt.',
        'opened' => 'Inventur eröffnet, Sollbestand eingefroren.',
        'saved' => 'Zählmengen gespeichert.',
        'applied' => 'Differenzen als Korrekturen gebucht.',
        'cycle' => 'Zyklus (ABC)',
        'cycle_open' => 'Zyklus zählen',
        'cycle_empty' => 'Keine fälligen Artikel in dieser Klasse.',
    ],
    'overview' => [
        'avg' => 'Ø-Preis',
        'value' => 'Wert',
        'priority' => 'Priorität',
        'min_stock' => 'Mindestbestand',
        'reorder_point' => 'Meldebestand',
        'release' => 'Freigeben',
        'set_levels' => 'Bestände festlegen',
        'reservations' => 'Reservierungen',
        'below_reorder' => 'Beschaffungsbedarf',
        'shortfall' => 'Fehlmenge',
        'no_reservations' => 'Keine aktiven Reservierungen.',
        'reservation_released' => 'Reservierung freigegeben.',
        'levels_saved' => 'Mindest-/Meldebestand gespeichert.',
    ],

    'serial' => [
        'title' => 'Seriennummern',
        'subtitle' => 'Einzelstück-Lebenslauf, Versandnachweis und Echtheitsprüfung.',
        'empty' => 'Keine Seriennummern vorhanden.',
        'blocked_default' => 'Gesperrt',
        'status' => [
            'created' => 'Erzeugt',
            'in_stock' => 'Auf Lager',
            'reserved' => 'Reserviert',
            'shipped' => 'Ausgeliefert',
            'returned' => 'Zurückgenommen',
            'blocked' => 'Gesperrt',
            'scrapped' => 'Verschrottet',
        ],
        'source' => [
            'manufactured' => 'Eigenfertigung',
            'purchased' => 'Zukauf',
        ],
        'field' => [
            'serial_no' => 'Seriennummer',
            'status' => 'Status',
            'source' => 'Herkunft',
            'article' => 'Artikel',
            'variant' => 'Variante',
            'warehouse' => 'Lagerort',
            'customer' => 'Kunde',
            'order' => 'Fertigungsauftrag',
            'delivery' => 'Auslieferung',
            'shipped_at' => 'Versendet am',
            'reason' => 'Grund',
        ],
        'action' => [
            'block' => 'Sperren',
            'unblock' => 'Entsperren',
            'scrap' => 'Verschrotten',
            'verify' => 'Geräte-Pass',
            'search' => 'Suchen',
        ],
        'flash' => [
            'blocked' => 'Seriennummer gesperrt.',
            'unblocked' => 'Seriennummer entsperrt.',
            'scrapped' => 'Seriennummer verschrottet.',
        ],
        'verify' => [
            'title' => 'Geräte-Pass / Echtheitsprüfung',
            'subtitle' => 'Seriennummer eingeben, um Status und Herkunft zu prüfen.',
            'placeholder' => 'Seriennummer …',
            'not_found' => 'Keine Seriennummer gefunden – Echtheit nicht bestätigt.',
            'found' => 'Seriennummer gefunden.',
        ],
    ],

    'outbox' => [
        'status' => [
            'pending' => 'Ausstehend',
            'processing' => 'In Zustellung',
            'confirmed' => 'Bestätigt',
            'failed' => 'Fehlgeschlagen',
            'compensation_required' => 'Kompensation nötig',
        ],
    ],

    'valuation' => [
        'method' => [
            'moving_average' => 'Gleitender Durchschnitt',
            'fifo' => 'FIFO',
            'fefo' => 'FEFO (Verfall zuerst)',
        ],
    ],

    'scan' => [
        'action' => [
            'receipt' => 'Wareneingang',
            'issue' => 'Entnahme',
            'transfer' => 'Umlagerung',
        ],
        'title' => 'Scannen',
        'subtitle' => 'Code scannen und buchen',
        'code' => 'Code',
        'qty' => 'Menge',
        'book' => 'Buchen',
        'action_label' => 'Aktion',
        'target' => 'Ziel-Lager',
        'invalid' => 'Ungültige Eingabe.',
        'booked' => 'Buchung erfasst.',
    ],

    'lot' => [
        'title' => 'Chargen',
        'subtitle' => 'Chargenbestand, Los-Split und -Merge',
        'empty' => 'Keine Chargen vorhanden.',
        'lot_no' => 'Charge',
        'article' => 'Artikel',
        'best_before' => 'MHD',
        'on_hand' => 'Bestand',
        'split' => 'Teilen',
        'merge' => 'Zusammenführen',
        'new_lot_no' => 'Neue Charge',
        'qty' => 'Menge',
        'from' => 'Von',
        'into' => 'Nach',
        'flash' => [
            'split' => 'Charge geteilt.',
            'merged' => 'Chargen zusammengeführt.',
            'unknown' => 'Unbekannte Charge.',
        ],
    ],

    'label_template' => [
        'title' => 'Etikettenvorlagen',
        'subtitle' => 'Layout, Papiergröße, QR und Felder je Vorlage',
        'add' => 'Vorlage anlegen',
        'empty' => 'Keine Etikettenvorlagen.',
        'name' => 'Name',
        'paper_size' => 'Papiergröße',
        'orientation' => 'Ausrichtung',
        'orientation_landscape' => 'Quer',
        'orientation_portrait' => 'Hoch',
        'with_qr' => 'QR-Code',
        'is_default' => 'Standardvorlage',
        'default' => 'Standard',
        'fields' => 'Felder',
        'delete' => 'Vorlage löschen',
        'field' => [
            'title' => 'Titel',
            'subtitle' => 'Untertitel',
            'code' => 'Code',
            'code_type' => 'Codetyp',
            'lines' => 'Zeilen',
        ],
        'flash' => [
            'saved' => 'Vorlage gespeichert.',
            'deleted' => 'Vorlage gelöscht.',
        ],
    ],
];
