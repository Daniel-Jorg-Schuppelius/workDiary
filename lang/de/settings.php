<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : settings.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 *
 * Labels und Hilfetexte für das Settings-Tab auf der Organisations-
 * Bearbeitungsseite. Pro Sprache identische Schlüsselstruktur.
 */

return [
    'tabs' => [
        'pagination' => 'Listen',
        'invoicing' => 'Rechnungen',
        'uploads' => 'Datei-Uploads',
        'validation' => 'Eingabe-Limits',
        'notifications' => 'Benachrichtigungen',
        'ui' => 'Oberfläche',
        'routing' => 'Routing & Karten',
    ],
    'hint' => 'Leer lassen, um den systemweiten Standardwert zu nutzen.',
    'pagination' => [
        'heading' => 'Listengrößen',
        'description' => 'Anzahl Einträge pro Seite in Übersichten.',
        'timesheets' => 'Stundenzettel',
        'duty_plans' => 'Dienstpläne',
        'customers' => 'Kunden',
        'customer_search' => 'Kundensuche (Type-Ahead)',
        'customer_attachments' => 'Kundenanhänge',
        'organizations' => 'Organisationen',
        'tours' => 'Touren',
        'vehicles' => 'Fahrzeuge',
        'tags' => 'Tags',
        'archive' => 'Archiv',
        'dashboard_recent' => 'Dashboard: zuletzt verwendete',
    ],
    'invoicing' => [
        'heading' => 'Rechnungs-Defaults',
        'description' => 'Werte, die beim Anlegen neuer Rechnungen vorbelegt werden.',
        'default_tax_rate' => 'Standard-Steuersatz (%)',
        'default_currency' => 'Standard-Währung (ISO-4217)',
        'time_unit' => 'Zeit-Einheit für Positionen',
    ],
    'einvoice' => [
        'heading' => 'E-Rechnung (XRechnung)',
        'description' => 'Verkäuferdaten für XRechnung-Ausgaben (EN 16931) lokal erstellter Rechnungen. Firmenname leer = Organisationsname.',
        'seller_name' => 'Firmenname',
        'street' => 'Straße und Hausnummer',
        'zip' => 'PLZ',
        'city' => 'Ort',
        'country' => 'Ländercode (ISO 3166-1)',
        'vat_id' => 'USt-IdNr.',
        'tax_number' => 'Steuernummer',
        'contact_name' => 'Kontakt: Name',
        'contact_email' => 'Kontakt: E-Mail',
        'contact_phone' => 'Kontakt: Telefon',
        'iban' => 'IBAN',
        'bic' => 'BIC',
        'account_holder' => 'Kontoinhaber',
        'payment_terms_days' => 'Zahlungsziel (Tage)',
        'small_business' => 'Kleinunternehmer (§ 19 UStG)',
        'small_business_hint' => 'Weist in der XRechnung die Steuerkategorie E (steuerbefreit) mit Befreiungstext nach § 19 UStG aus.',
    ],
    'uploads' => [
        'heading' => 'Upload-Größenlimits (KB)',
        'description' => 'Maximale Dateigrößen für Uploads, in Kilobyte.',
        'csv_import_kb' => 'CSV-Import',
        'customer_attachment_kb' => 'Kundenanhang',
    ],
    'validation' => [
        'heading' => 'Eingabelängen',
        'description' => 'Zeichen- und Bereichslimits für Formularfelder.',
        'attendance' => [
            'heading' => 'Zeiterfassung',
            'note_max' => 'Notiz, max. Zeichen',
            'device_max' => 'Geräte-ID, max. Zeichen',
            'break_minutes_max' => 'Pause, max. Minuten',
        ],
        'tag' => [
            'heading' => 'Tags',
            'name_max' => 'Tag-Name, max. Zeichen',
        ],
        'comment' => [
            'heading' => 'Kommentare',
            'body_max' => 'Kommentartext, max. Zeichen',
        ],
        'duty_plan' => [
            'heading' => 'Dienstpläne',
            'note_max' => 'Notiz, max. Zeichen',
        ],
    ],
    'notifications' => [
        'heading' => 'Push-Benachrichtigungen',
        'description' => 'Verhalten von Push-Nachrichten.',
        'push' => [
            'body_truncate' => 'Nachrichten-Vorschau, max. Zeichen',
        ],
    ],
    'ui' => [
        'heading' => 'Oberflächen-Verhalten',
        'description' => 'Visuelles und interaktives Verhalten der App.',
        'calendar' => [
            'heading' => 'Kalender',
            'slot_minutes' => 'Slot-Länge in Minuten',
        ],
        'dashboard' => [
            'heading' => 'Dashboard',
            'recent_limit' => 'Anzahl letzter Einträge',
        ],
        'search' => [
            'heading' => 'Suche',
            'results_limit' => 'Standard-Treffer-Limit',
        ],
    ],
    'reset' => 'Auf Standard zurücksetzen',
    'placeholder_default' => 'Standard :value',
    'routing' => [
        'nominatim' => [
            'heading' => 'Nominatim (Geocoding)',
            'base_url' => 'Basis-URL',
            'email' => 'Kontakt-E-Mail',
            'rate_limit_per_sec' => 'Anfragen pro Sekunde',
        ],
        'osrm' => [
            'heading' => 'OSRM (Routing)',
            'base_url' => 'Basis-URL',
            'profile' => 'Profil (z. B. driving)',
            'timeout' => 'Timeout (Sekunden)',
        ],
        'tiles' => [
            'heading' => 'Kartenkacheln',
            'url' => 'Tile-URL-Vorlage',
            'max_zoom' => 'Maximaler Zoom',
        ],
    ],
];
