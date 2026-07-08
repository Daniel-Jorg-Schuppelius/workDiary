<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : privacy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

/**
 * Datenkategorien für die Datenschutzseite (MVP-005, §3.2).
 *
 * Reine Anzeige-Konfiguration — keine Laufzeit-Logik leitet daraus
 * Lösch-/Sperrverhalten ab. Aufbewahrungsfristen sind Vorschläge nach
 * deutschem Recht (GoBD); rechtsverbindliche Anpassung pro Kunde im
 * Folge-MVP über `organizations.settings[privacy]`.
 *
 * Sensibilitätsstufen:
 *  - "high"     hoch (z. B. personenbezogene Mitarbeiterdaten)
 *  - "special"  besonders sensibel (z. B. Krankmeldungen, Signaturen)
 *  - "medium"   mittel
 *  - "low"      gering
 */
return [
    'categories' => [
        [
            'code' => 'employees',
            'label' => 'Mitarbeitende',
            'models' => ['User', 'UserGroup'],
            'sensitivity' => 'high',
            'retention' => 'bis Vertragsende',
            'delete_path' => 'Org-Admin → Mitglieder',
        ],
        [
            'code' => 'working_time',
            'label' => 'Arbeitszeit',
            'models' => ['Timesheet', 'Attendance'],
            'sensitivity' => 'high',
            'retention' => '10 Jahre (GoBD)',
            'retention_area' => 'gobd_financial',
            'delete_path' => 'nach Lock gesperrt, nicht löschbar',
        ],
        [
            'code' => 'absences',
            'label' => 'Lohnabwesenheiten',
            'models' => ['SickLeave', 'Vacation'],
            'sensitivity' => 'special',
            'retention' => 'gemäß Tarif/Gesetz',
            'delete_path' => 'Org-Admin nach Frist',
        ],
        [
            'code' => 'diary',
            'label' => 'Auftragsbuch',
            'models' => ['DiaryEntry', 'Comment'],
            'sensitivity' => 'medium',
            'retention' => '5 Jahre (konfigurierbar)',
            'delete_path' => 'Org-Admin',
        ],
        [
            'code' => 'tours',
            'label' => 'Touren / Standorte',
            'models' => ['TravelLog', 'Tour'],
            'sensitivity' => 'high',
            'retention' => '2 Jahre (Vorschlag)',
            'delete_path' => 'automatischer Löschlauf',
        ],
        [
            'code' => 'expenses',
            'label' => 'Spesen / Reisekosten',
            'models' => ['Expense', 'PerDiemTrip', 'PerDiemDay'],
            'sensitivity' => 'high',
            'retention' => '10 Jahre (GoBD)',
            'retention_area' => 'gobd_financial',
            'delete_path' => 'gesperrt, archiviert',
        ],
        [
            'code' => 'customers',
            'label' => 'Kundenstamm',
            'models' => ['Customer'],
            'sensitivity' => 'medium',
            'retention' => 'bis Geschäftsbeziehung + Frist',
            'delete_path' => 'Org-Admin',
        ],
        [
            'code' => 'attachments',
            'label' => 'Anhänge',
            'models' => ['Attachment'],
            'sensitivity' => 'depends',
            'retention' => 'mit übergeordnetem Datensatz',
            'delete_path' => 'gemeinsam mit Owner-Record',
        ],
        [
            'code' => 'signatures',
            'label' => 'Unterschriften',
            'models' => ['ProtocolSignature'],
            'sensitivity' => 'special',
            'retention' => 'wie Auftrag',
            'delete_path' => 'gemeinsam mit Auftrag',
        ],
        [
            'code' => 'qualifications',
            'label' => 'Qualifikationen',
            'models' => ['Qualification'],
            'sensitivity' => 'high',
            'retention' => 'bis Vertragsende',
            'delete_path' => 'Org-Admin',
        ],
        [
            'code' => 'push',
            'label' => 'Push-Subscriptions',
            'models' => ['PushSubscription'],
            'sensitivity' => 'low',
            'retention' => 'bei Abmeldung',
            'delete_path' => 'automatisch bei Logout/Cleanup',
        ],
        [
            'code' => 'audit',
            'label' => 'Audit-Protokoll',
            'models' => ['AuditLog'],
            'sensitivity' => 'high',
            'retention' => '24 Monate (Vorschlag)',
            'delete_path' => 'systemseitiger Rotations-Job',
        ],
    ],

    /**
     * Betriebsmodus zur Anzeige im Kopfbereich. Frei wählbar — typische
     * Werte: 'saas' | 'private_cloud' | 'on_premise'.
     */
    'operating_mode' => env('PRIVACY_OPERATING_MODE', 'on_premise'),

    /**
     * Optionaler Verweis auf AVV/DPA-Dokument (Link wird im Kopfbereich
     * angezeigt). NULL = nicht hinterlegt.
     */
    'dpa_document_url' => env('PRIVACY_DPA_URL'),
];
