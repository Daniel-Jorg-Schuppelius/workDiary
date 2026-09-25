<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : anlagenwartung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'anlagenwartung',
    'label' => 'Maschinenbau und Anlagenwartung',
    'description' => 'Maschinenbau und Anlagenwartung: Wartungsverträge, Störungen, Inspektionen, Kalibrierung und Ersatzteile mit SLA und Prüfintervallen.',
    // v2: Default-Eintragstypen (Struktur-Typen) ans Profil gekoppelt.
    'version' => 2,
    // Default-Struktur-Typen (EntryTypeSeeder::profiles()) — nicht die
    // Classification-Domäne entry_type.
    'entry_type_defaults' => ['general', 'service', 'hvac_job'],
    // Feature 081 (MVP-373): empfohlener Funktionsumfang — als vorausgewählte
    // Checkliste auf der Seite „Funktionsumfang“, nie still angewendet.
    'modules_recommended' => [
        'module.planung',
        'module.spesen',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.knowledge',
        'module.auswertungen_team',
        'module.fuhrpark',
        'module.liegenschaften',
        'module.asset_compliance',
        'module.lager',
        'module.contracts',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'stoerung', 'label' => 'Störung'],
            ['code' => 'inspektion', 'label' => 'Inspektion'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'inbetriebnahme', 'label' => 'Inbetriebnahme'],
            ['code' => 'kalibrierung', 'label' => 'Kalibrierung'],
            ['code' => 'stillstand', 'label' => 'Stillstand'],
            ['code' => 'ersatzteil', 'label' => 'Ersatzteil'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
        ],
        'activity' => [
            ['code' => 'pruefen', 'label' => 'Prüfen'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'schmieren', 'label' => 'Schmieren'],
            ['code' => 'tauschen', 'label' => 'Tauschen'],
            ['code' => 'justieren', 'label' => 'Justieren'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'kalibrieren', 'label' => 'Kalibrieren'],
            ['code' => 'testen', 'label' => 'Testen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'lagerschaden', 'label' => 'Lagerschaden'],
            ['code' => 'leckage', 'label' => 'Leckage'],
            ['code' => 'sensorfehler', 'label' => 'Sensorfehler'],
            ['code' => 'ueberhitzung', 'label' => 'Überhitzung'],
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'vibrationsproblem', 'label' => 'Vibrationsproblem'],
            ['code' => 'steuerungsfehler', 'label' => 'Steuerungsfehler'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'material', 'label' => 'Materialfehler'],
            ['code' => 'wartungUeberfaellig', 'label' => 'Wartung überfällig'],
            ['code' => 'fremdteil', 'label' => 'Fremdteil'],
            ['code' => 'prozessabweichung', 'label' => 'Prozessabweichung'],
        ],
        'result' => [
            ['code' => 'behoben', 'label' => 'Behoben'],
            ['code' => 'teilBehoben', 'label' => 'Teilweise behoben'],
            ['code' => 'produktiv', 'label' => 'Anlage produktiv'],
            ['code' => 'stillstand', 'label' => 'Stillstand'],
            ['code' => 'ersatzteilNoetig', 'label' => 'Ersatzteil nötig'],
            ['code' => 'beobachtung', 'label' => 'Beobachtung'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'maschine', 'label' => 'Maschine'],
            ['code' => 'anlage', 'label' => 'Anlage'],
            ['code' => 'pumpe', 'label' => 'Pumpe'],
            ['code' => 'motor', 'label' => 'Motor'],
            ['code' => 'sensor', 'label' => 'Sensor'],
            ['code' => 'steuerung', 'label' => 'Steuerung'],
            ['code' => 'hydraulik', 'label' => 'Hydraulik'],
            ['code' => 'pneumatik', 'label' => 'Pneumatik'],
            ['code' => 'foerdertechnik', 'label' => 'Fördertechnik'],
            ['code' => 'roboter', 'label' => 'Roboter'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'wartung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'wartung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'kalibrierung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'kalibrierung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'ersatzteil',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stillstand',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'AW_WARTUNG',
            'name' => 'Anlagenwartung nach Plan',
            'domain' => 'anlagenwartung',
            'risk_level' => 'normal',
            'description' => 'Geführte Wartung nach Herstellervorgabe inkl. Messwerten und Probelauf.',
            'steps' => [
                ['code' => 'freischalten', 'step_type' => 'confirm', 'label' => 'Anlage freischalten und gegen Wiedereinschalten sichern'],
                ['code' => 'wartung', 'step_type' => 'confirm', 'label' => 'Wartungsarbeiten nach Herstellervorgabe durchführen'],
                ['code' => 'messwerte', 'step_type' => 'text', 'label' => 'Messwerte erfassen (Soll/Ist)'],
                ['code' => 'probelauf', 'step_type' => 'confirm', 'label' => 'Probelauf durchführen'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'AW_STOERUNG',
            'name' => 'Störungsbeseitigung',
            'domain' => 'anlagenwartung',
            'risk_level' => 'normal',
            'description' => 'Strukturierte Störungsaufnahme mit Diagnose, Befunddokumentation und Maßnahme.',
            'steps' => [
                ['code' => 'aufnahme', 'step_type' => 'text', 'label' => 'Störung und Stillstandszeit erfassen'],
                ['code' => 'diagnose', 'step_type' => 'confirm', 'label' => 'Fehlerdiagnose durchführen'],
                ['code' => 'befund', 'step_type' => 'photo', 'label' => 'Befund dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'massnahme', 'step_type' => 'text', 'label' => 'Maßnahme dokumentieren'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'AW_INSPEKTION',
            'name' => 'Inspektion (Sicht- und Funktionsprüfung)',
            'domain' => 'anlagenwartung',
            'risk_level' => 'normal',
            'description' => 'Zustandsfeststellung ohne Eingriff mit Befund und Empfehlung zur Instandsetzung.',
            'steps' => [
                ['code' => 'anlage', 'step_type' => 'text', 'label' => 'Anlage und Prüfumfang erfassen'],
                ['code' => 'sicht', 'step_type' => 'confirm', 'label' => 'Sichtprüfung auf Beschädigung, Korrosion und Undichtigkeit'],
                ['code' => 'funktion', 'step_type' => 'confirm', 'label' => 'Funktionsprüfung im Betrieb'],
                ['code' => 'befund', 'step_type' => 'photo', 'label' => 'Befund dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'empfehlung', 'step_type' => 'text', 'label' => 'Empfehlung zur Instandsetzung festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'AW_KALIBRIERUNG',
            'name' => 'Kalibrierung Mess- und Regeltechnik',
            'domain' => 'anlagenwartung',
            'risk_level' => 'normal',
            'description' => 'Abgleich gegen ein Referenznormal mit Messwerten vor und nach der Justierung.',
            'steps' => [
                ['code' => 'referenz', 'step_type' => 'text', 'label' => 'Referenznormal und Kalibrierschein-Nummer erfassen'],
                ['code' => 'vorher', 'step_type' => 'text', 'label' => 'Messwerte vor der Justierung erfassen (Soll/Ist)'],
                ['code' => 'justierung', 'step_type' => 'confirm', 'label' => 'Justierung durchführen (falls außerhalb der Toleranz)'],
                ['code' => 'nachher', 'step_type' => 'text', 'label' => 'Messwerte nach der Justierung erfassen (Soll/Ist)'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten (innerhalb/außerhalb der Toleranz)'],
            ],
        ],
        [
            'code' => 'AW_ERSATZTEIL',
            'name' => 'Ersatzteiltausch',
            'domain' => 'anlagenwartung',
            'risk_level' => 'normal',
            'description' => 'Tausch mit Material- und Seriennummernnachweis, Probelauf und Verbleib des Altteils.',
            'steps' => [
                ['code' => 'freischalten', 'step_type' => 'confirm', 'label' => 'Anlage freischalten und gegen Wiedereinschalten sichern'],
                ['code' => 'altteil', 'step_type' => 'text', 'label' => 'Altteil mit Seriennummer erfassen'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Ersatzteil erfassen'],
                ['code' => 'einbau', 'step_type' => 'confirm', 'label' => 'Ersatzteil einbauen'],
                ['code' => 'probelauf', 'step_type' => 'confirm', 'label' => 'Probelauf durchführen'],
                ['code' => 'verbleib', 'step_type' => 'choice', 'label' => 'Verbleib des Altteils festhalten'],
            ],
        ],
        [
            'code' => 'AW_INBETRIEBNAHME',
            'name' => 'Inbetriebnahme',
            'domain' => 'anlagenwartung',
            'risk_level' => 'high',
            'description' => 'Erstinbetriebnahme mit Prüfung, Einweisung des Betreibers und Abnahme.',
            'steps' => [
                ['code' => 'pruefung', 'step_type' => 'confirm', 'label' => 'Anschlüsse und Schutzeinrichtungen geprüft'],
                ['code' => 'parameter', 'step_type' => 'text', 'label' => 'Betriebsparameter einstellen und erfassen'],
                ['code' => 'probelauf', 'step_type' => 'confirm', 'label' => 'Probelauf unter Last durchführen'],
                ['code' => 'einweisung', 'step_type' => 'confirm', 'label' => 'Betreiber eingewiesen, Dokumentation übergeben'],
                ['code' => 'abnahme', 'step_type' => 'signature', 'label' => 'Abnahme durch den Betreiber', 'requires_proof_type' => 'signature'],
            ],
        ],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'AW_WARTUNGSPROTOKOLL'],
        ['code' => 'AW_STOERBERICHT'],
        ['code' => 'AW_MESSPROTOKOLL'],
        ['code' => 'AW_KALIBRIERNACHWEIS'],
        ['code' => 'AW_ERSATZTEILNACHWEIS'],
        ['code' => 'AW_ABNAHME'],
    ],
    'asset_categories' => [
        'servicefahrzeug',
        'messgeraet',
        'drehmomentschluessel',
        'kalibriergeraet',
        'diagnoseLaptop',
        'werkzeugwagen',
        'endoskopkamera',
    ],
    'tags_seed' => [
        '#wartung',
        '#stillstand',
        '#ersatzteil',
        '#messwerte',
        '#kalibrierung',
        '#anlagenakte',
        '#notfall',
    ],
    'maintenance_plans_seed' => [
        ['code' => 'AW-MESSGERAET-12M', 'label' => 'Messgeräte-Kalibrierung jährlich', 'category_code' => 'messgeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'AW-KALIBRIER-12M', 'label' => 'Kalibriergerät rekalibrieren', 'category_code' => 'kalibriergeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'AW-DREHMOMENT-12M', 'label' => 'Drehmomentschlüssel prüfen (VDI/VDE 2645)', 'category_code' => 'drehmomentschluessel', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'AW-FAHRZEUG-12M', 'label' => 'Servicefahrzeug UVV-Prüfung', 'category_code' => 'servicefahrzeug', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
    ],
    'sla_contracts_seed' => [
        [
            'code' => 'SLA-AW-STANDARD',
            'label' => 'Anlagenwartung Standard-SLA',
            'is_default' => true,
            'priority_table' => [
                'low'    => ['reaction_minutes' => 1440, 'resolution_minutes' => 10080],
                'normal' => ['reaction_minutes' => 480,  'resolution_minutes' => 2880],
                'high'   => ['reaction_minutes' => 240,  'resolution_minutes' => 1440],
                'urgent' => ['reaction_minutes' => 60,   'resolution_minutes' => 480],
            ],
            'business_hours' => [
                ['weekday' => 1, 'from' => '06:00', 'to' => '18:00'],
                ['weekday' => 2, 'from' => '06:00', 'to' => '18:00'],
                ['weekday' => 3, 'from' => '06:00', 'to' => '18:00'],
                ['weekday' => 4, 'from' => '06:00', 'to' => '18:00'],
                ['weekday' => 5, 'from' => '06:00', 'to' => '16:00'],
            ],
        ],
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): B2B-Industrieservice —
    // wenig eigene personenbezogene Verarbeitung, GVV/DSFA standardmäßig aus.
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required', 'active' => false],
        ['key' => 'dpia_required', 'active' => false],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],
    // Vertragsvorlagen (MVP-893): Laufzeit, Kündigung, Pflichten relativ zum Beginn.
    'contract_templates' => [
        [
            'name' => 'Wartungsvertrag Anlage',
            'kind' => 'maintenance',
            'title' => 'Wartungsvertrag',
            'term_kind' => 'fixed',
            'min_term_months' => 12,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 90,
            'value_period' => 'yearly',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Jährliche Wartung', 'offset_months' => 11, 'recurring' => true, 'recurrence_months' => 12],
                ['kind' => 'indexation', 'title' => 'Preisanpassung prüfen', 'offset_months' => 11, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
    ],
];
