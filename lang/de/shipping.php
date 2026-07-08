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
    'intro' => 'Carrier-Anbindungen für Versandlabels und Sendungsverfolgung (DHL Paket u. a.). Je Carrier eine Anbindung pro Organisation; Zugangsdaten werden verschlüsselt gespeichert.',

    'form_heading' => 'Anbindung anlegen / bearbeiten',
    'form_hint' => 'Wähle den Carrier und hinterlege die Zugangsdaten. Eine erneute Speicherung mit demselben Carrier aktualisiert die bestehende Anbindung.',
    'secret_hint' => 'Passwort und API-Schlüssel werden verschlüsselt abgelegt und nie wieder angezeigt. Beim Bearbeiten leer lassen, um die gespeicherten Werte zu behalten.',
    'connections_heading' => 'Bestehende Anbindungen',
    'no_connections' => 'Noch keine Carrier-Anbindung hinterlegt.',

    'field' => [
        'carrier' => 'Carrier',
        'name' => 'Bezeichnung',
        'username' => 'Benutzer (GK-Zugang)',
        'password' => 'Passwort',
        'api_key' => 'API-Schlüssel (dhl-api-key)',
        'billing_number' => 'Abrechnungsnummer',
        'sandbox' => 'Sandbox / Testumgebung',
        'active' => 'Aktiv',
        'weight_grams' => 'Gewicht (g)',
    ],

    // Kurzlabel für die Versand-Statusanzeige (Rang 20).
    'label_short' => 'Versand',

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
        'create' => 'Versand',
    ],

    'flash' => [
        'saved' => 'Carrier-Anbindung gespeichert.',
        'disconnected' => 'Carrier-Anbindung deaktiviert.',
        'credentials_required' => 'Benutzer, Passwort und API-Schlüssel sind für eine neue Anbindung erforderlich.',
        'no_recipient' => 'Die Auslieferung hat keinen Kunden als Empfänger.',
        'already_created' => 'Zu dieser Auslieferung besteht bereits ein Versandauftrag.',
        'no_connection' => 'Für den gewählten Carrier ist keine aktive Anbindung hinterlegt.',
        'label_created' => 'Versandauftrag erstellt und Label abgerufen.',
        'label_failed' => 'Versandlabel konnte nicht erstellt werden: :reason',
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
];
