<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shipping.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'Versand & Logistik',
    'intro' => 'Carrier-Anbindungen für Versandlabels und Sendungsverfolgung (DHL Paket, UPS, FedEx). Je Carrier eine Anbindung pro Organisation; Zugangsdaten werden verschlüsselt gespeichert.',

    'form_heading' => 'Anbindung anlegen',
    'form_heading_edit' => 'Anbindung :carrier bearbeiten',
    'form_hint' => 'Wählen Sie den Carrier und hinterlegen Sie die Zugangsdaten. Bestehende Anbindungen ändern Sie über „Bearbeiten“ in der Liste.',
    'secret_hint' => 'Passwort und API-Schlüssel werden verschlüsselt abgelegt und nie wieder angezeigt. Beim Bearbeiten leer lassen, um die gespeicherten Werte zu behalten.',
    'connections_heading' => 'Bestehende Anbindungen',
    'no_connections' => 'Noch keine Carrier-Anbindung hinterlegt.',

    'field' => [
        'carrier' => 'Carrier',
        'name' => 'Bezeichnung',
        'username' => 'Benutzer / Client-ID',
        'password' => 'Passwort / Client-Secret',
        'api_key' => 'API-Schlüssel (nur DHL: dhl-api-key)',
        'returns_receiver_id' => 'Retourenempfänger-ID (nur DHL)',
        'returns_receiver_id_hint' => 'Im DHL-Geschäftskundenportal angelegter Retourenempfänger; nötig für Retourenlabels.',
        'billing_number' => 'Abrechnungs-/Kontonummer',
        'sandbox' => 'Sandbox / Testumgebung',
        'active' => 'Aktiv',
        'weight_grams' => 'Gewicht (g)',
        'length_cm' => 'Länge (cm)',
        'width_cm' => 'Breite (cm)',
        'height_cm' => 'Höhe (cm)',
    ],

    // Kurzlabel für die Versand-Statusanzeige (Rang 20).
    'label_short' => 'Versand',
    'last_tracked' => 'Zuletzt abgeglichen: :time',
    'confirm_cancel' => 'Versandauftrag beim Carrier stornieren? Das Label wird ungültig; danach können Sie einen neuen Versandauftrag erstellen.',

    'col' => [
        'mode' => 'Modus',
        'status' => 'Status',
    ],

    'mode' => [
        'sandbox' => 'Sandbox',
        'production' => 'Produktiv',
    ],

    'status_label' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
    ],

    'action' => [
        'save' => 'Speichern',
        'disconnect' => 'Deaktivieren',
        'edit' => 'Bearbeiten',
        'cancel_edit' => 'Abbrechen',
        'download_label' => 'Label herunterladen',
        'track_now' => 'Sendungsstatus abrufen',
        'cancel_shipment' => 'Versand stornieren',
        'create' => 'Versand',
    ],

    'flash' => [
        'saved' => 'Carrier-Anbindung gespeichert.',
        'disconnected' => 'Carrier-Anbindung deaktiviert.',
        'credentials_required' => 'Für eine neue Anbindung sind Benutzer/Client-ID und Passwort/Client-Secret erforderlich (DHL zusätzlich: API-Schlüssel).',
        'no_recipient' => 'Die Auslieferung hat keinen Kunden als Empfänger.',
        'already_created' => 'Zu dieser Auslieferung besteht bereits ein Versandauftrag.',
        'no_connection' => 'Für den gewählten Carrier ist keine aktive Anbindung hinterlegt.',
        'label_created' => 'Versandauftrag erstellt und Label abgerufen.',
        'label_failed' => 'Versandlabel konnte nicht erstellt werden: :reason',
        'tracked' => 'Sendungsstatus abgerufen: :status',
        'track_failed' => 'Sendungsstatus konnte nicht abgerufen werden: :reason',
        'cancelled' => 'Versandauftrag storniert.',
        'cancel_failed' => 'Versandauftrag konnte nicht storniert werden: :reason',
        'not_cancellable' => 'Die Sendung ist bereits beim Carrier und kann nicht mehr storniert werden.',
        'exists_use_edit' => 'Für diesen Carrier besteht bereits eine Anbindung. Bitte ändern Sie sie über „Bearbeiten“.',
    ],

    'notify' => [
        'delivery_problem' => [
            'title' => 'Zustellproblem bei einer Sendung',
            'message' => 'Sendung :tracking (:carrier) meldet ein Zustellproblem.',
        ],
    ],

    // Sendungsstatus (ShipmentStatus).
    'status' => [
        'draft' => 'Entwurf',
        'labeled' => 'Label erstellt',
        'in_transit' => 'Unterwegs',
        'delivered' => 'Zugestellt',
        'problem' => 'Zustellproblem',
        'cancelled' => 'Storniert',
    ],
    'parcel' => [
        'add' => 'Packstück hinzufügen',
        'edit' => 'Packstück :no bearbeiten',
        'delete' => 'Packstück löschen',
        'confirm_delete' => 'Packstück :no löschen? Die Seriennummern werden wieder frei.',
        'label' => 'Packstück :no von :of',
        'serials' => 'Seriennummern im Packstück',
        'no_serials' => 'Diese Auslieferung hat keine freien Seriennummern.',
        'serial_count' => ':count Seriennr.',
        'saved' => 'Packstück gespeichert.',
        'deleted' => 'Packstück gelöscht.',
        'serial_not_allowed' => 'Seriennummern müssen aus dieser Auslieferung stammen und dürfen in keinem anderen Packstück liegen.',
        'locked' => 'Für diese Auslieferung besteht bereits ein Versandauftrag; die Packstücke sind fest.',
    ],
    'customs' => [
        'action' => 'Zollpapiere',
        'dialog_title' => 'Zollpapiere erstellen',
        'required_hint' => 'Ziel außerhalb der EU: Für die Ausfuhr sind Zollpapiere nötig.',
        'eu_hint' => 'Ziel innerhalb der EU: In der Regel sind keine Zollpapiere nötig. Ausnahmen sind Gebiete außerhalb des Zollgebiets wie die Kanarischen Inseln oder Helgoland.',
        'reason' => 'Versandgrund',
        'reason_hint' => 'Beim Verkauf entsteht eine Handelsrechnung, sonst eine Proformarechnung. Der Grund wird an der Auslieferung gespeichert.',
        'submit' => 'PDF erstellen',
        'commercial_invoice' => 'Handelsrechnung',
        'proforma_invoice' => 'Proformarechnung',
        'value' => 'Warenwert (Preis)',
        'reasons' => [
            'sale' => 'Verkauf',
            'gift' => 'Geschenk',
            'sample' => 'Warenmuster',
            'documents' => 'Dokumente',
            'returned_goods' => 'Rücksendung',
            'repair' => 'Reparatur',
            'other' => 'Sonstiges',
        ],
        'error' => [
            'no_customer' => 'Die Auslieferung hat keinen Empfänger.',
            'missing' => 'Für die Zollpapiere fehlen zu „:article“: :fields.',
        ],
        'pdf' => [
            'date' => 'Datum',
            'delivery_note' => 'Lieferschein',
            'invoice' => 'Rechnung',
            'tracking' => 'Sendungsnummer',
            'sender' => 'Absender',
            'recipient' => 'Empfänger',
            'vat_id' => 'USt-IdNr.',
            'eori' => 'EORI-Nummer',
            'reason' => 'Versandgrund',
            'currency' => 'Währung',
            'col' => [
                'description' => 'Warenbeschreibung',
                'tariff' => 'Zolltarifnummer',
                'origin' => 'Ursprungsland',
                'quantity' => 'Menge',
                'net_weight' => 'Nettogewicht (kg)',
                'unit_value' => 'Einzelwert',
                'total_value' => 'Gesamtwert',
            ],
            'total_net_weight' => 'Nettogewicht gesamt',
            'gross_weight' => 'Bruttogewicht',
            'parcels' => 'Packstücke',
            'total_value' => 'Gesamtwert',
            'no_sale' => 'Kein Verkauf — der Wert dient nur der Zollabfertigung.',
            'declaration' => 'Wir versichern, dass die Angaben in diesem Dokument richtig und vollständig sind.',
            'place_date' => 'Ort, Datum',
            'signature' => 'Unterschrift',
        ],
    ],
];
