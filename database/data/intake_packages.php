<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : intake_packages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

// Katalog der Prüfpakete als Profildaten (Feature 200, MVP-1675). Profile
// nennen im Abschnitt `intake_packages` nur den Code (und ob das Paket gleich
// eingeschaltet wird); der IntakePackageInstallStep holt die Daten von hier.
// Felder im Format des Feldschemas (FieldSchema::fromRows), Unterlagen mit
// Schlüssel, enge Sichtbarkeit je Art (`health`, `identity`), Übernahme an den
// Kunden bei der Umwandlung. Das GwG-Paket ist eine Klasse
// (GwgIntakeCheckPackage) und steht deshalb nicht hier.
//
// Pakete sind Arbeitshilfen, keine rechtliche Vorgabe. Eine eigene
// Aufbewahrungsregel tragen sie bewusst nicht: Gesundheitsangaben einer
// abgelehnten Aufnahme gehen mit der Anonymisierung des Leads.

return [
    // Ambulante Pflege: Erstaufnahme. Pflegegrad und Leistungsbezug speisen bei
    // der Umwandlung die Klassifikation `pflegegrad` und das Merkmal
    // `benefit` des Regelsatzes `home_care` (Feature 197, § 37 Abs. 3 SGB XI).
    'care_admission' => [
        'label' => 'Pflege-Erstaufnahme',
        'requires_decision' => true,
        'refresh_months' => 12,
        'fields' => [
            ['key' => 'care', 'label' => 'Pflegegrad und Leistungen', 'type' => 'section'],
            ['key' => 'care_grade', 'label' => 'Pflegegrad', 'type' => 'choice', 'required' => true,
                'options' => ['pg1', 'pg2', 'pg3', 'pg4', 'pg5'],
                'option_labels' => ['pg1' => 'Pflegegrad 1', 'pg2' => 'Pflegegrad 2', 'pg3' => 'Pflegegrad 3', 'pg4' => 'Pflegegrad 4', 'pg5' => 'Pflegegrad 5']],
            ['key' => 'care_grade_notice_on', 'label' => 'Datum des Bescheids', 'type' => 'date'],
            ['key' => 'benefit', 'label' => 'Leistungsbezug', 'type' => 'choice', 'required' => true,
                'options' => ['care_allowance', 'combination', 'in_kind'],
                'option_labels' => ['care_allowance' => 'Pflegegeld (§ 37 SGB XI)', 'combination' => 'Kombinationsleistung (§ 38 SGB XI)', 'in_kind' => 'Pflegesachleistung (§ 36 SGB XI)'],
                'help' => 'Wird bei der Umwandlung Merkmal des Pflege-Regelsatzes (Beratungsbesuch nach § 37 Abs. 3 SGB XI).'],
            ['key' => 'representation', 'label' => 'Vollmachten und Betreuung', 'type' => 'section'],
            ['key' => 'power_of_attorney', 'label' => 'Vorsorgevollmacht liegt vor', 'type' => 'boolean'],
            ['key' => 'attorney_name', 'label' => 'Bevollmächtigte Person', 'type' => 'text', 'visible_if' => ['field' => 'power_of_attorney', 'op' => 'filled']],
            ['key' => 'legal_guardian', 'label' => 'Gesetzliche Betreuung eingerichtet', 'type' => 'boolean'],
            ['key' => 'guardian_name', 'label' => 'Betreuerin bzw. Betreuer', 'type' => 'text', 'visible_if' => ['field' => 'legal_guardian', 'op' => 'filled']],
            ['key' => 'advance_directive', 'label' => 'Patientenverfügung vorhanden', 'type' => 'boolean'],
            ['key' => 'contacts', 'label' => 'Kontakte', 'type' => 'section'],
            ['key' => 'emergency_contacts', 'label' => 'Notfallkontakte (Name, Beziehung, Telefon)', 'type' => 'textarea', 'required' => true],
            ['key' => 'family_doctor', 'label' => 'Hausärztin bzw. Hausarzt', 'type' => 'text'],
            ['key' => 'contract', 'label' => 'Pflegevertrag', 'type' => 'section'],
            ['key' => 'care_contract', 'label' => 'Pflegevertrag (Nummer bzw. Verweis)', 'type' => 'text',
                'help' => 'Der Vertrag entsteht aus der Vertragsvorlage des Profils; hier steht nur der Verweis.'],
        ],
        'documents' => [
            ['key' => 'care_notice', 'label' => 'Bescheid über den Pflegegrad'],
            ['key' => 'power_of_attorney', 'label' => 'Vorsorgevollmacht bzw. Betreuerausweis'],
            ['key' => 'advance_directive', 'label' => 'Patientenverfügung (Kopie)'],
            ['key' => 'medication_plan', 'label' => 'Medikationsplan'],
            ['key' => 'signed_contract', 'label' => 'Unterschriebener Pflegevertrag'],
        ],
        // Gesundheitsangaben (Art. 9 DSGVO): nur mit dem Recht `lead.intake.health`, verschlüsselt.
        'sensitive' => [
            'health' => ['care_grade', 'care_grade_notice_on', 'benefit', 'legal_guardian', 'guardian_name', 'advance_directive', 'family_doctor',
                'doc_care_notice', 'doc_power_of_attorney', 'doc_advance_directive', 'doc_medication_plan'],
        ],
        'transfer' => [
            'deadline_traits' => ['home_care' => ['benefit' => 'benefit']],
            'classifications' => ['pflegegrad' => 'care_grade'],
        ],
    ],

    // B2B-Dienstleister (IT, Gebäudereinigung, Handwerk): Neukundenprüfung.
    // Die USt-IdNr. wird nur formal geprüft (Aufbau und Prüfziffer, kein
    // BZSt-Abruf); das Zahlungsziel ist ein Vorschlag für den Kunden. Kein
    // Kreditlimit (Entscheidung Feature 200).
    'b2b_new_customer' => [
        'label' => 'B2B-Neukunde',
        'requires_decision' => false,
        'refresh_months' => 24,
        'fields' => [
            ['key' => 'b2b', 'label' => 'Neukundenprüfung', 'type' => 'section'],
            ['key' => 'vat_id', 'label' => 'USt-IdNr.', 'type' => 'text', 'extension' => 'vat_id',
                'help' => 'Formale Prüfung von Aufbau und Prüfziffer — keine qualifizierte Bestätigung beim Bundeszentralamt für Steuern.'],
            ['key' => 'credit_source', 'label' => 'Bonitätsauskunft: Quelle', 'type' => 'text'],
            ['key' => 'credit_checked_on', 'label' => 'Bonitätsauskunft: Datum', 'type' => 'date'],
            ['key' => 'credit_result', 'label' => 'Bonitätsauskunft: Ergebnis', 'type' => 'choice',
                'options' => ['good', 'acceptable', 'poor', 'none'],
                'option_labels' => ['good' => 'gut', 'acceptable' => 'ausreichend', 'poor' => 'schwach', 'none' => 'keine Auskunft']],
            ['key' => 'payment_terms_days', 'label' => 'Zahlungsziel (Tage)', 'type' => 'number', 'min' => 0, 'max' => 365,
                'help' => 'Vorschlag für das Zahlungsziel des neuen Kunden.'],
            ['key' => 'prepayment', 'label' => 'Vorkasse', 'type' => 'boolean'],
        ],
        'documents' => [
            ['key' => 'credit_report', 'label' => 'Bonitätsauskunft'],
            ['key' => 'register_extract', 'label' => 'Handelsregisterauszug'],
        ],
        'transfer' => [
            'customer' => ['vat_id' => 'vat_id', 'payment_terms_days' => 'payment_terms_days'],
        ],
    ],
];
