<?php
/*
 * Created on   : Fri Oct 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    // Löschbereiche der Aufbewahrung (Schlüssel == config/retention.php areas.*).
    'retention_area' => [
        'audit_logs' => 'Audit-Protokoll',
        'exports' => 'Lohn-/Zeitexporte',
        'gobd_financial' => 'Steuerlich relevante Daten',
        'claims' => 'Reklamationen (abgeschlossen)',
        'leads' => 'Leads (nicht konvertiert)',
        'applications' => 'Bewerbungen (abgelehnt/zurückgezogen)',
        'privacy_requests' => 'Betroffenenanfragen (abgeschlossen)',
        'cti_calls' => 'CTI-Anrufmetadaten',
        'idea_maps' => 'Ideenkarten (Papierkorb)',
        'dictations' => 'Sprachdiktate (Transkripte)',
        'problem_reports' => 'Fehlerberichte (geschlossen)',
        'driver_license_checks' => 'Führerscheinkontrollen',
        'documents_invoice' => 'Rechnungen (DMS)',
        'passenger_rides' => 'Fahrtakten (Orts-/Fahrgastbezug)',
        'employee_records' => 'Personalstamm (ausgeschiedene Mitarbeiter)',
        'personnel_files' => 'Personalakten (ausgeschiedene Mitarbeiter)',
        'customer_master' => 'Kundenstamm (ohne Geschäftsvorfälle)',
        'customer_intakes' => 'Kundeneingänge (abgelehnt oder zurückgenommen)',
        'time_records' => 'Arbeitszeit-Rohdaten (Anwesenheiten)',
        'location_points' => 'Standort-Rohdaten (GPS-Punkte)',
        'documents_general' => 'Dokumente (ohne Steuer-/Handelsrecht-Bezug)',
        'learning_records' => 'Lernplattform (Einschreibungen, Lernzeit, Versuche)',
        'learning_certificates' => 'Lernplattform-Zertifikate (Pseudonymisierung)',
    ],

    // Standardnamen des Anforderungskatalogs (Schlüssel == config/dataprotection.php compliance.requirements.*).
    'requirement' => [
        'avv_required' => 'AVV mit Auftragsverarbeiter',
        'avv_current' => 'AVV gültig (nicht abgelaufen)',
        'gvv_required' => 'GVV mit gemeinsam Verantwortlichem',
        'dpia_required' => 'DSFA bei DSFA-Bedarf',
        'tom_assigned' => 'TOM je Verarbeitungstätigkeit',
        'tom_proof_current' => 'TOM-Nachweise gültig (nicht abgelaufen)',
    ],
    // Datenkategorien der Datenschutz-Übersicht (Schlüssel == config/privacy.php categories.*.code).
    'category' => [
        'employees' => ['label' => 'Mitarbeitende', 'retention' => 'bis Vertragsende', 'delete_path' => 'Org-Admin → Mitglieder'],
        'working_time' => ['label' => 'Arbeitszeit', 'retention' => '10 Jahre (GoBD)', 'delete_path' => 'nach Lock gesperrt, nicht löschbar'],
        'absences' => ['label' => 'Lohnabwesenheiten', 'retention' => 'gemäß Tarif/Gesetz', 'delete_path' => 'Org-Admin nach Frist'],
        'diary' => ['label' => 'Auftragsbuch', 'retention' => '5 Jahre (konfigurierbar)', 'delete_path' => 'Org-Admin'],
        'tours' => ['label' => 'Touren / Standorte', 'retention' => '2 Jahre (Vorschlag)', 'delete_path' => 'automatischer Löschlauf'],
        'expenses' => ['label' => 'Spesen / Reisekosten', 'retention' => '10 Jahre (GoBD)', 'delete_path' => 'gesperrt, archiviert'],
        'customers' => ['label' => 'Kundenstamm', 'retention' => 'bis Geschäftsbeziehung + Frist', 'delete_path' => 'Org-Admin'],
        'attachments' => ['label' => 'Anhänge', 'retention' => 'mit übergeordnetem Datensatz', 'delete_path' => 'gemeinsam mit dem übergeordneten Datensatz'],
        'signatures' => ['label' => 'Unterschriften', 'retention' => 'wie Auftrag', 'delete_path' => 'gemeinsam mit Auftrag'],
        'qualifications' => ['label' => 'Qualifikationen', 'retention' => 'bis Vertragsende', 'delete_path' => 'Org-Admin'],
        'push' => ['label' => 'Push-Abonnements', 'retention' => 'bei Abmeldung', 'delete_path' => 'automatisch bei Abmeldung/Bereinigung'],
        'audit' => ['label' => 'Audit-Protokoll', 'retention' => '24 Monate (Vorschlag)', 'delete_path' => 'systemseitiger Rotations-Job'],
    ],
    'retention_years' => ':years Jahre',
    // Befunde der Lückenanalyse (ComplianceAnalysisService, gespeichert in der Sprache der Organisation).
    'gap' => [
        'avv_missing' => 'Auftragsverarbeiter „:name“ ohne AVV',
        'avv_expiring' => 'AVV „:name“ läuft ab oder ist abgelaufen',
        'gvv_missing' => 'Gemeinsam Verantwortlicher „:name“ ohne GVV',
        'dpia_missing' => '„:name“ mit DSFA-Bedarf ohne abgeschlossene DSFA',
        'tom_missing' => '„:name“ ohne zugeordnete TOM',
        'tom_proof_expiring' => 'TOM-Nachweise laufen ab oder sind abgelaufen: :names',
    ],
];
