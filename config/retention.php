<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : retention.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/*
 * Aufbewahrungsfristen je Rechtsraum (Restpunkt 67): Matrix Bereich ×
 * Region (DE/AT/CH) → Jahre + Rechtsgrundlage. Aufgelöst über
 * organizations.legal_region (Fallback default_region) durch die
 * RetentionRegistry; der Retention-Scan (Restpunkt 66) erzeugt daraus
 * Lösch-VORSCHLÄGE (Review-Queue statt Direktlöschung).
 *
 * Bewusst NICHT hier: location.retention_days (Rohdaten-Purge, eigene
 * Datenschutz-Semantik) und whistleblowing.retention_months (HinSchG §11,
 * pro Org überschreibbar) — beide behalten ihre eigene Mechanik.
 */
return [
    'default_region' => env('RETENTION_DEFAULT_REGION', 'DE'),

    'areas' => [
        // Audit-Protokoll: append-only mit Hash-Kette — wird NICHT gelöscht
        // (audit:verify!), die Frist dient Anzeige/Export-Manifest.
        'audit_logs' => [
            'label' => 'Audit-Protokoll',
            'years' => ['DE' => 10, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => 'GoBD / AO §147', 'AT' => 'BAO §132', 'CH' => 'OR Art. 958f'],
        ],

        // Lohn-/Zeitexporte (Dateien + Läufe): steuerlich relevante Unterlagen.
        'exports' => [
            'label' => 'Lohn-/Zeitexporte',
            'years' => ['DE' => 10, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => 'GoBD / AO §147', 'AT' => 'BAO §132', 'CH' => 'OR Art. 958f'],
        ],

        // Steuerlich relevante Bewegungsdaten (Zeiterfassung, Spesen) —
        // referenziert aus config/privacy.php categories (retention_area).
        'gobd_financial' => [
            'label' => 'Steuerlich relevante Daten',
            'years' => ['DE' => 10, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => 'GoBD / AO §147', 'AT' => 'BAO §132', 'CH' => 'OR Art. 958f'],
        ],

        // Abgelehnte/zurückgezogene Bewerbungen: AGG-/Klagefrist —
        // die konkrete Vormerkung steht am Datensatz (retention_until),
        // dieser Katalogeintrag dokumentiert Bereich + Rechtsgrundlage.
        // Reklamationsakten (Feature 072): Korrespondenz ist Handels-/
        // Geschäftsbrief (6 J., § 257 HGB); folgt eine Gutschrift, gilt für
        // den BELEG die Belegfrist im Rechnungsbereich (8 J., BEG IV) —
        // zwei Fristklassen, der Beleg lebt im Faktura-Modul.
        'claims' => [
            'label' => 'Reklamationen (abgeschlossen)',
            'years' => ['DE' => 6, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => '§ 257 HGB (Geschäftsbriefe, 6 J.)', 'AT' => '§ 212 UGB (7 J.)', 'CH' => 'OR 958f (10 J.)'],
        ],

        'applications' => [
            'label' => 'Bewerbungen (abgelehnt/zurückgezogen)',
            'years' => ['DE' => 1, 'AT' => 1, 'CH' => 1],
            'basis' => ['DE' => 'AGG §15 Abs. 4 / ArbGG §61b (Praxis 4–6 Monate)', 'AT' => 'GlBG §15 (Praxis 7 Monate)', 'CH' => 'DSG (Zweckbindung)'],
        ],

        // Abgeschlossene Betroffenenanfragen: Nachweis der Erfüllung.
        'privacy_requests' => [
            'label' => 'Betroffenenanfragen (abgeschlossen)',
            'years' => ['DE' => 3, 'AT' => 3, 'CH' => 5],
            'basis' => ['DE' => 'Art. 5 Abs. 2 DSGVO / §195 BGB', 'AT' => 'Art. 5 Abs. 2 DSGVO / §1489 ABGB', 'CH' => 'Art. 127 OR'],
        ],

        // CTI-Anrufmetadaten (Feature 056, MVP-118; Vollaudit 2026-07 M18):
        // Verbindungsdaten (Rufnummer) werden anonymisiert, die Notiz-Zeile
        // (Richtung/Zeitpunkt/Dauer) bleibt als Vorgangsnachweis.
        'cti_calls' => [
            'label' => 'CTI-Anrufmetadaten',
            'years' => ['DE' => 1, 'AT' => 1, 'CH' => 1],
            'basis' => ['DE' => 'Art. 5 Abs. 1 lit. e DSGVO (Speicherbegrenzung)', 'AT' => 'Art. 5 DSGVO', 'CH' => 'DSG (Zweckbindung)'],
        ],

        // Ideenkarten im Papierkorb (Feature 054, MVP-110; Vollaudit 2026-07
        // M21): soft-gelöschte Karten werden nach Frist endgültig entfernt.
        'idea_maps' => [
            'label' => 'Ideenkarten (Papierkorb)',
            'years' => ['DE' => 1, 'AT' => 1, 'CH' => 1],
            'basis' => ['DE' => 'Art. 17 DSGVO (Löschkonzept)', 'AT' => 'Art. 17 DSGVO', 'CH' => 'DSG'],
        ],

        // Fehlerberichte mit Seitenkontext-PII (Vollaudit 2026-07, N15).
        'problem_reports' => [
            'label' => 'Fehlerberichte (geschlossen)',
            'years' => ['DE' => 2, 'AT' => 2, 'CH' => 2],
            'basis' => ['DE' => 'Art. 5 Abs. 1 lit. e DSGVO', 'AT' => 'Art. 5 DSGVO', 'CH' => 'DSG'],
        ],

        // Führerscheinkontrollen (Phase 38, MVP-417; Vollaudit 2026-07 N24):
        // Halterhaftungs-Nachweis, danach Löschvorschlag über den Review-Scan.
        'driver_license_checks' => [
            'label' => 'Führerscheinkontrollen',
            'years' => ['DE' => 2, 'AT' => 2, 'CH' => 2],
            'basis' => ['DE' => '§ 21 StVG (Halterhaftung, Nachweis)', 'AT' => '§ 103 KFG', 'CH' => 'SVG Art. 95'],
        ],

        // Eingangsrechnungen im DMS (DocumentType::Invoice).
        'documents_invoice' => [
            'label' => 'Rechnungen (DMS)',
            'years' => ['DE' => 10, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => 'GoBD / AO §147 / §14b UStG', 'AT' => 'BAO §132', 'CH' => 'OR Art. 958f'],
        ],

        // Fahrtakten (MVP-456, Konzept §11): Orts-/Fahrgastbezug wird nach
        // Frist anonymisiert (Abhol-/Zieladresse, Wegpunkte, Fahrgastkontakt,
        // Freitexte); Beträge, Steuer und Zeiten bleiben als kaufmännischer
        // Nachweis. Frist folgt der Aufbewahrungspflicht des Mietwagen-
        // Auftragseingangs (§ 49 Abs. 4 PBefG: 1 Jahr).
        'passenger_rides' => [
            'label' => 'Fahrtakten (Orts-/Fahrgastbezug)',
            'years' => ['DE' => 1, 'AT' => 1, 'CH' => 1],
            'basis' => ['DE' => '§ 49 Abs. 4 PBefG (1 J.) / Art. 5 Abs. 1 lit. e DSGVO', 'AT' => 'Art. 5 DSGVO (Speicherbegrenzung)', 'CH' => 'DSG (Zweckbindung)'],
        ],
    ],
];
