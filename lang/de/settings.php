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
        'region' => 'Region & Feiertage',
        'weather' => 'Wetter',
        'maintenance' => 'Wartung',
    ],
    'region' => [
        'heading' => 'Rechtsraum & Feiertage',
        'description' => 'Bestimmt, welche gesetzlichen Feiertage gelten. Wirkt auf Feiertagszuschläge, die Dienstplan-Compliance und Feiertagsanzeigen.',
        'holiday_provider' => 'Feiertagsregion (Land / Bundesland)',
        'holiday_provider_hint' => 'Regionale Feiertage wie Fronleichnam oder Reformationstag gelten nur in bestimmten Bundesländern. Leer = systemweiter Standard.',
    ],
    'weather' => [
        'heading' => 'Wetter-Auto-Abruf',
        'description' => 'Bei Anlage eines Protokolls automatisch einen Wetter-Snapshot für Standort und Zeitpunkt ziehen — als Beweiswert. Projekte können das überschreiben.',
        'auto_fetch' => 'Wetter bei Protokoll-Anlage automatisch abrufen',
        'auto_fetch_hint' => 'Nur wenn Standort-Koordinaten vorliegen; sonst passiert nichts. Standard: aus.',
        'provider' => 'Wetterdienst',
        'provider_hint' => 'Open-Meteo: weltweit, ohne Anmeldung. DWD Open Data: amtliche deutsche Stationsdaten (CC BY 4.0, Quellenvermerk „Deutscher Wetterdienst“), nur für Standorte in Deutschland mit Station in Reichweite.',
        'dwd_max_station_km' => 'DWD: maximale Stationsentfernung (km)',
        'dwd_max_station_km_hint' => 'Liegt keine aktive DWD-Station innerhalb dieser Entfernung, entsteht kein Snapshot — lieber kein Wert als ein falscher. Standard: 30 km.',
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
        'remote_pending_groups' => 'Fernwartungs-Inbox: unzugeordnete Geräte',
        'remote_shared_devices' => 'Fernwartungs-Inbox: Mehrkundengeräte',
        'remote_shared_sessions' => 'Fernwartungs-Inbox: Sitzungen je Gerätekarte',
    ],
    'invoicing' => [
        'heading' => 'Rechnungs-Defaults',
        'description' => 'Werte, die beim Anlegen neuer Rechnungen vorbelegt werden.',
        'default_tax_rate' => 'Standard-Steuersatz (%)',
        'default_currency' => 'Standard-Währung (ISO-4217)',
        'time_unit' => 'Zeit-Einheit für Positionen',
        'billing_increment_minutes' => 'Standard-Taktung (Minuten)',
        'billing_increment_minutes_hint' => 'Aufrundung abrechenbarer Zeit. Greift, wenn weder Projekt noch Kunde eine Taktung setzen. Leer = minutengenau.',
        'billing_grouping_gap_minutes' => 'Standard-Lücke zum Zusammenfassen (Minuten)',
        'billing_grouping_gap_minutes_hint' => 'Bis zu dieser Lücke werden Einträge beim Abrechnen zu einem Block zusammengefasst. Leer = keine Zusammenfassung.',
        'default_hourly_rate' => 'Standard-Stundensatz (Erlös)',
        'default_hourly_rate_hint' => 'Greift, wenn weder Eintrag, Kundenkondition, Mitarbeiter, Tätigkeit, Projekt noch Kunde einen Satz setzen. Leer = ohne Satz bleiben Zeiten bei 0,00 €.',
    ],
    'time_import' => [
        'heading' => 'Zeit-Import',
        'description' => 'Wie importierte Zeiten (Fernwartung, Toggl, Kimai, …) einem Projekt zugeordnet werden.',
        'keyword_matching' => 'Zeiten anhand von Schlüsselwörtern dem Projekt zuordnen',
        'keyword_matching_hint' => 'Enthält der Text einer importierten Zeit den Namen oder ein Schlüsselwort eines Projekts desselben Kunden, wird sie dort gebucht statt im Standardprojekt bzw. in der Zuordnungs-Inbox. Nur eindeutige Treffer buchen.',
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
        'attachment_kb' => 'Anhänge (allgemein)',
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
    'maintenance' => [
        'heading' => 'Wartungsmodus',
        'description' => 'Sperrt die Anwendung für alle Nicht-Administratoren dieses Mandanten (503-Wartungsseite). Administratoren arbeiten weiter und sehen einen Hinweis-Banner.',
        'enabled' => 'Wartungsmodus aktivieren',
        'message' => 'Hinweistext für die Wartungsseite',
        'message_placeholder' => 'z. B. Geplante Wartung — wir sind in Kürze wieder da.',
        'until' => 'Voraussichtliches Ende',
        'until_hint' => 'Optional. Nach diesem Zeitpunkt endet der Wartungsmodus automatisch.',
        'block_ingest' => 'Auch Terminal-/Webhook-Eingänge pausieren',
        'block_ingest_hint' => 'Standard: aus — Stempelterminals, Telefonie- und Standort-Eingänge laufen während der Wartung weiter.',
    ],
];
