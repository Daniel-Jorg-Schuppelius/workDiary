<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : facility.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'facility',
    'label' => 'Facility Management und Hausmeisterdienste',
    'description' => 'Facility Management und Hausmeisterdienste: Objektkontrolle, Mängel, Kleinreparaturen, Wartungsrunden, Winterdienst und Zählerstände.',
    // v2 (Feature 100): Entsorgungs-Modul empfohlen + AVV-Presets für
    // Leuchtmittel/Batterien/Verpackungen (Objekt-Räumungen).
    'version' => 2,
    // Feature 082 (MVP-379): vorgeschlagener Start-Arbeitsbereich. Nur ein
    // Default-Vorschlag (D16), greift solange die Org keinen eigenen Default
    // setzt und der Nutzer keine eigene Wahl getroffen hat.
    'nav_focus_default' => 'facility',
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
        'module.liegenschaften',
        'module.fuhrpark',
        'module.standorterfassung',
        'module.contracts',
        'module.entsorgung',
    ],
    'classifications' => [
        // Feature 100: FM-typische Ergänzungen zu den AVV-Plattform-Defaults.
        'waste_code' => [
            ['code' => 'avv_150110_h', 'label' => '15 01 10* — Verpackungen mit gefährlichen Restanhaftungen'],
            ['code' => 'avv_200133_h', 'label' => '20 01 33* — Gemischte Batterien (mit gefährlichen)'],
            ['code' => 'avv_200134', 'label' => '20 01 34 — Batterien (nicht gefährlich)'],
        ],
        'entry_type' => [
            ['code' => 'objektkontrolle', 'label' => 'Objektkontrolle'],
            ['code' => 'maengelmeldung', 'label' => 'Mängelmeldung'],
            ['code' => 'kleinreparatur', 'label' => 'Kleinreparatur'],
            ['code' => 'wartungsrunde', 'label' => 'Wartungsrunde'],
            ['code' => 'winterdienst', 'label' => 'Winterdienst'],
            ['code' => 'zaehlerstand', 'label' => 'Zählerstand'],
            ['code' => 'schluessel', 'label' => 'Schlüsselausgabe/-rückgabe'],
            ['code' => 'notfall', 'label' => 'Notfall'],
        ],
        'activity' => [
            ['code' => 'kontrollieren', 'label' => 'Kontrollieren'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'reparieren', 'label' => 'Reparieren'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'abschliessen', 'label' => 'Abschließen'],
            ['code' => 'streuen', 'label' => 'Streuen'],
            ['code' => 'ablesen', 'label' => 'Ablesen'],
            ['code' => 'beauftragen', 'label' => 'Beauftragen'],
            ['code' => 'nachhalten', 'label' => 'Nachhalten'],
        ],
        'defect_type' => [
            ['code' => 'defekteBeleuchtung', 'label' => 'Defekte Beleuchtung'],
            ['code' => 'wasserschaden', 'label' => 'Wasserschaden'],
            ['code' => 'vandalismus', 'label' => 'Vandalismus'],
            ['code' => 'schliessproblem', 'label' => 'Schließproblem'],
            ['code' => 'brandschutzmangel', 'label' => 'Brandschutzmangel'],
            ['code' => 'stolperstelle', 'label' => 'Stolperstelle'],
            ['code' => 'verunreinigung', 'label' => 'Verunreinigung'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'nutzung', 'label' => 'Nutzung'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
            ['code' => 'mangelndeWartung', 'label' => 'Mangelnde Wartung'],
            ['code' => 'unbekannt', 'label' => 'Unbekannt'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'offen', 'label' => 'Offen'],
            ['code' => 'weitergeleitet', 'label' => 'Weitergeleitet'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'materialFehlt', 'label' => 'Material fehlt'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'gebaeude', 'label' => 'Gebäude'],
            ['code' => 'tuer', 'label' => 'Tür'],
            ['code' => 'tor', 'label' => 'Tor'],
            ['code' => 'beleuchtung', 'label' => 'Beleuchtung'],
            ['code' => 'heizung', 'label' => 'Heizung'],
            ['code' => 'aufzug', 'label' => 'Aufzug'],
            ['code' => 'aussenanlage', 'label' => 'Außenanlage'],
            ['code' => 'brandschutz', 'label' => 'Brandschutz'],
            ['code' => 'schluessel', 'label' => 'Schlüssel'],
            ['code' => 'zaehler', 'label' => 'Zähler'],
        ],
        // Gewerke / Nachunternehmer-Kategorien (Auswahl je Eintrag; konkreter
        // Betrieb liegt im Lieferanten-/Nachunternehmer-Stamm).
        'trade' => [
            ['code' => 'reinigung', 'label' => 'Reinigung'],
            ['code' => 'sicherheitsdienst', 'label' => 'Sicherheitsdienst'],
            ['code' => 'haustechnik', 'label' => 'Haustechnik / HLK'],
            ['code' => 'aufzug', 'label' => 'Aufzugswartung'],
            ['code' => 'gruenpflege', 'label' => 'Grünpflege'],
            ['code' => 'brandschutz', 'label' => 'Brandschutz'],
            ['code' => 'schaedlingsbekaempfung', 'label' => 'Schädlingsbekämpfung'],
            ['code' => 'entsorgung', 'label' => 'Entsorgung'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'objektkontrolle',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'objektkontrolle',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maengelmeldung',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maengelmeldung',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maengelmeldung',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'kleinreparatur',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'schluessel',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'winterdienst',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'winterdienst',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'notfall',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'FM_OBJEKTKONTROLLE',
            'name' => 'Objektkontrolle / Begehungsrunde',
            'domain' => 'facility',
            'risk_level' => 'normal',
            'description' => 'Strukturierte Objektbegehung mit Mängelerfassung.',
            'steps' => [
                ['code' => 'rundgang', 'step_type' => 'confirm', 'label' => 'Rundgang nach Plan durchführen'],
                ['code' => 'brandschutz', 'step_type' => 'confirm', 'label' => 'Brandschutz-/Fluchtwege kontrollieren'],
                ['code' => 'maengel', 'step_type' => 'photo', 'label' => 'Mängel dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'FM_SCHLUESSEL',
            'name' => 'Schlüsselausgabe/-rücknahme',
            'domain' => 'facility',
            'risk_level' => 'normal',
            'description' => 'Nachvollziehbare Schlüsselübergabe mit Unterschrift.',
            'steps' => [
                ['code' => 'schluessel', 'step_type' => 'text', 'label' => 'Schlüssel-/Schließnummer erfassen'],
                ['code' => 'empfaenger', 'step_type' => 'text', 'label' => 'Empfänger erfassen'],
                ['code' => 'quittung', 'step_type' => 'signature', 'label' => 'Quittung unterschreiben', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'FM_MAENGEL',
            'name' => 'Mängelerfassung',
            'domain' => 'facility',
            'risk_level' => 'normal',
            'description' => 'Mangel aufnehmen, einstufen und an die zuständige Stelle weiterleiten.',
            'steps' => [
                ['code' => 'ort', 'step_type' => 'text', 'label' => 'Ort des Mangels erfassen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Mangel fotografieren', 'requires_proof_type' => 'photo'],
                ['code' => 'dringlichkeit', 'step_type' => 'choice', 'label' => 'Dringlichkeit einstufen'],
                ['code' => 'sicherung', 'step_type' => 'confirm', 'label' => 'Gefahrenstelle gesichert (falls nötig)', 'required' => false, 'blocking' => false],
                ['code' => 'zustaendigkeit', 'step_type' => 'choice', 'label' => 'Zuständigkeit festlegen (eigene Behebung, Gewerk, Eigentümer)'],
            ],
        ],
        [
            'code' => 'FM_KLEINREPARATUR',
            'name' => 'Kleinreparatur',
            'domain' => 'facility',
            'risk_level' => 'normal',
            'description' => 'Reparatur ohne Fachgewerk mit Material- und Fotonachweis.',
            'steps' => [
                ['code' => 'auftrag', 'step_type' => 'text', 'label' => 'Schaden und Auftrag erfassen'],
                ['code' => 'vorher', 'step_type' => 'photo', 'label' => 'Zustand vorher dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Material erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'reparatur', 'step_type' => 'confirm', 'label' => 'Reparatur durchführen'],
                ['code' => 'nachher', 'step_type' => 'photo', 'label' => 'Zustand nachher dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'FM_WINTERDIENST',
            'name' => 'Winterdienst',
            'domain' => 'facility',
            'risk_level' => 'high',
            'description' => 'Räum- und Streueinsatz mit Zeit-, Witterungs- und Streumittelnachweis (Verkehrssicherungspflicht).',
            'steps' => [
                ['code' => 'witterung', 'step_type' => 'text', 'label' => 'Witterung und Temperatur erfassen'],
                ['code' => 'beginn', 'step_type' => 'text', 'label' => 'Einsatzbeginn erfassen'],
                ['code' => 'flaechen', 'step_type' => 'confirm', 'label' => 'Flächen nach Räumplan geräumt und gestreut'],
                ['code' => 'streumittel', 'step_type' => 'material', 'label' => 'Streumittel erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'nachweis', 'step_type' => 'photo', 'label' => 'Zustand der Flächen dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'ende', 'step_type' => 'text', 'label' => 'Einsatzende erfassen'],
            ],
        ],
        [
            'code' => 'FM_ZAEHLERSTAND',
            'name' => 'Zählerablesung',
            'domain' => 'facility',
            'risk_level' => 'low',
            'description' => 'Ablesung mit Foto des Zählwerks als Nachweis.',
            'steps' => [
                ['code' => 'zaehler', 'step_type' => 'text', 'label' => 'Zähler- bzw. Messstellennummer erfassen'],
                ['code' => 'stand', 'step_type' => 'number', 'label' => 'Zählerstand erfassen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Zählwerk fotografieren', 'requires_proof_type' => 'photo'],
                ['code' => 'plausibel', 'step_type' => 'confirm', 'label' => 'Plausibilität gegenüber der Vorablesung geprüft'],
            ],
        ],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'fm_brandschutz', 'kind' => 'operatorDuty', 'label' => 'Brandschutzkontrolle (Betreiberpflicht)', 'level' => 'monatlich'],
        ['code' => 'fm_wartungsrunde', 'kind' => 'technicalInspection', 'label' => 'Wartungsrunde / technische Prüfung'],
        ['code' => 'fm_gesperrt', 'kind' => 'accessRestriction', 'label' => 'Gesperrter Bereich', 'note' => 'Zutritt nur nach Freigabe.'],
        ['code' => 'fm_stoerungsmeldung', 'kind' => 'other', 'label' => 'Störungsmeldung hinterlegt'],
    ],
    // 'protocol_templates': Codes aus database/data/protocol_templates.php (MVP-902).
    // 'asset_categories' installiert der BranchProfileInstaller nicht (Kategorien
    // stammen aus config('asset_categories')); sie dienen als Branchen-Taxonomie.
    'protocol_templates' => [
        ['code' => 'FM_OBJEKTBERICHT'],
        ['code' => 'FM_MAENGELPROTOKOLL'],
        ['code' => 'FM_SCHLUESSELNACHWEIS'],
        ['code' => 'FM_WINTERDIENSTNACHWEIS'],
        ['code' => 'FM_ZAEHLERABLESUNG'],
        ['code' => 'FM_NOTFALLBERICHT'],
    ],
    'asset_categories' => [
        'servicefahrzeug',
        'werkzeugkoffer',
        'leiter',
        'schneefraese',
        'streuwagen',
        'schluesselkasten',
        'zaehlerkamera',
        'funkgeraet',
    ],
    'tags_seed' => [
        '#objektkontrolle',
        '#winterdienst',
        '#schluessel',
        '#mangel',
        '#notfall',
        '#brandschutz',
        '#weitergeleitet',
    ],
    'maintenance_plans_seed' => [
        ['code' => 'FM-LEITER-12M', 'label' => 'Leiterprüfung jährlich (DGUV)', 'category_code' => 'leiter', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'FM-SCHNEEFRAESE-06M', 'label' => 'Schneefräse Saisonwartung', 'category_code' => 'schneefraese', 'interval_kind' => 'months', 'interval_value' => 6, 'tolerance_days' => 14],
        ['code' => 'FM-STREUWAGEN-12M', 'label' => 'Streuwagen Jahreswartung', 'category_code' => 'streuwagen', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'FM-FUNK-12M', 'label' => 'Funkgerät Funktionsprüfung', 'category_code' => 'funkgeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 7],
    ],
    'sla_contracts_seed' => [
        [
            'code' => 'SLA-FM-STANDARD',
            'label' => 'FM-Standard-SLA',
            'is_default' => true,
            'priority_table' => [
                'low'    => ['reaction_minutes' => 1440, 'resolution_minutes' => 10080],
                'normal' => ['reaction_minutes' => 480,  'resolution_minutes' => 2880],
                'high'   => ['reaction_minutes' => 120,  'resolution_minutes' => 1440],
                'urgent' => ['reaction_minutes' => 30,   'resolution_minutes' => 240],
            ],
            'business_hours' => [
                ['weekday' => 1, 'from' => '07:00', 'to' => '17:00'],
                ['weekday' => 2, 'from' => '07:00', 'to' => '17:00'],
                ['weekday' => 3, 'from' => '07:00', 'to' => '17:00'],
                ['weekday' => 4, 'from' => '07:00', 'to' => '17:00'],
                ['weekday' => 5, 'from' => '07:00', 'to' => '16:00'],
            ],
        ],
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): wenig eigene
    // Datenverarbeitung → GVV/DSFA standardmäßig aus (aktivierbar).
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required', 'active' => false],
        ['key' => 'dpia_required', 'active' => false],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],

    // Eigene Felder je Träger (MVP-868): ergänzt nur fehlende Schlüssel.
    'custom_fields' => [
        'assets' => [
            ['label' => 'Gebäudeteil', 'type' => 'text'],
            ['label' => 'Wartungsvertrag', 'type' => 'boolean'],
        ],
        'projects' => [
            ['label' => 'Objektnummer', 'type' => 'text'],
        ],
    ],
    // Vertragsvorlagen (MVP-893): Laufzeit, Kündigung, Pflichten relativ zum Beginn.
    'contract_templates' => [
        [
            'name' => 'Hausmeistervertrag',
            'kind' => 'service',
            'title' => 'Hausmeister- und Objektbetreuung',
            'term_kind' => 'fixed',
            'min_term_months' => 12,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 90,
            'value_period' => 'monthly',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Leistungsverzeichnis überprüfen', 'offset_months' => 11, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
    ],
];
