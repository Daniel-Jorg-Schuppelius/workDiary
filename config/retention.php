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

        // Abgeschlossene Betroffenenanfragen: Nachweis der Erfüllung.
        'privacy_requests' => [
            'label' => 'Betroffenenanfragen (abgeschlossen)',
            'years' => ['DE' => 3, 'AT' => 3, 'CH' => 5],
            'basis' => ['DE' => 'Art. 5 Abs. 2 DSGVO / §195 BGB', 'AT' => 'Art. 5 Abs. 2 DSGVO / §1489 ABGB', 'CH' => 'Art. 127 OR'],
        ],

        // Eingangsrechnungen im DMS (DocumentType::Invoice).
        'documents_invoice' => [
            'label' => 'Rechnungen (DMS)',
            'years' => ['DE' => 10, 'AT' => 7, 'CH' => 10],
            'basis' => ['DE' => 'GoBD / AO §147 / §14b UStG', 'AT' => 'BAO §132', 'CH' => 'OR Art. 958f'],
        ],
    ],
];
