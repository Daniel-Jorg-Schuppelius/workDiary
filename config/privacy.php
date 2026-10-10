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
    // Bezeichnung, Aufbewahrung und Löschweg je Code: lang/<sprache>/privacy.php category.*.
    'categories' => [
        [
            'code' => 'employees',
            'models' => ['User', 'UserGroup'],
            'sensitivity' => 'high',
        ],
        [
            'code' => 'working_time',
            'models' => ['Timesheet', 'Attendance'],
            'sensitivity' => 'high',
            'retention_area' => 'gobd_financial',
        ],
        [
            'code' => 'absences',
            'models' => ['SickLeave', 'Vacation'],
            'sensitivity' => 'special',
        ],
        [
            'code' => 'diary',
            'models' => ['DiaryEntry', 'Comment'],
            'sensitivity' => 'medium',
        ],
        [
            'code' => 'tours',
            'models' => ['TravelLog', 'Tour'],
            'sensitivity' => 'high',
        ],
        [
            'code' => 'expenses',
            'models' => ['Expense', 'PerDiemTrip', 'PerDiemDay'],
            'sensitivity' => 'high',
            'retention_area' => 'gobd_financial',
        ],
        [
            'code' => 'customers',
            'models' => ['Customer'],
            'sensitivity' => 'medium',
        ],
        [
            'code' => 'attachments',
            'models' => ['Attachment'],
            'sensitivity' => 'depends',
        ],
        [
            'code' => 'signatures',
            'models' => ['ProtocolSignature'],
            'sensitivity' => 'special',
        ],
        [
            'code' => 'qualifications',
            'models' => ['Qualification'],
            'sensitivity' => 'high',
        ],
        [
            'code' => 'push',
            'models' => ['PushSubscription'],
            'sensitivity' => 'low',
        ],
        [
            'code' => 'audit',
            'models' => ['AuditLog'],
            'sensitivity' => 'high',
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
