<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sicherheitsdienst.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'sicherheitsdienst',
    'label' => 'Sicherheitsdienst und Objektschutz',
    'version' => 1,
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
        'module.standorterfassung',
        'module.fuhrpark',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'wachbuch', 'label' => 'Wachbuch-Eintrag'],
            ['code' => 'revierfahrt', 'label' => 'Revierfahrt'],
            ['code' => 'kontrollgang', 'label' => 'Kontrollgang'],
            ['code' => 'alarm', 'label' => 'Alarmverfolgung'],
            ['code' => 'zutritt', 'label' => 'Zutrittskontrolle'],
            ['code' => 'schluessel', 'label' => 'Schlüsselausgabe/-rückgabe'],
            ['code' => 'vorfall', 'label' => 'Vorfallmeldung'],
            ['code' => 'uebergabe', 'label' => 'Schichtübergabe'],
            ['code' => 'sonderdienst', 'label' => 'Sonderdienst'],
        ],
        'activity' => [
            ['code' => 'kontrollieren', 'label' => 'Kontrollieren'],
            ['code' => 'anmelden', 'label' => 'Anmelden'],
            ['code' => 'absichern', 'label' => 'Absichern'],
            ['code' => 'melden', 'label' => 'Melden'],
            ['code' => 'eskortieren', 'label' => 'Eskortieren'],
            ['code' => 'sperren', 'label' => 'Sperren'],
            ['code' => 'aufschliessen', 'label' => 'Aufschließen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'eskalieren', 'label' => 'Eskalieren'],
        ],
        'defect_type' => [
            ['code' => 'einbruchVerdacht', 'label' => 'Einbruchsverdacht'],
            ['code' => 'vandalismus', 'label' => 'Vandalismus'],
            ['code' => 'tueroffen', 'label' => 'Tür/Fenster offen'],
            ['code' => 'alarmAusgeloest', 'label' => 'Alarm ausgelöst'],
            ['code' => 'schluesselFehlt', 'label' => 'Schlüssel fehlt'],
            ['code' => 'personenkonflikt', 'label' => 'Personenkonflikt'],
            ['code' => 'brandschutz', 'label' => 'Brandschutzmangel'],
        ],
        'root_cause' => [
            ['code' => 'unbekannt', 'label' => 'Unbekannt'],
            ['code' => 'technischerFehler', 'label' => 'Technischer Fehler'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'organisatorisch', 'label' => 'Organisatorisch'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'lageGeklaert', 'label' => 'Lage geklärt'],
            ['code' => 'polizeiInformiert', 'label' => 'Polizei informiert'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'offen', 'label' => 'Offen'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
            ['code' => 'fehlalarm', 'label' => 'Fehlalarm'],
        ],
        'product_group' => [
            ['code' => 'objekt', 'label' => 'Objekt'],
            ['code' => 'kontrollpunkt', 'label' => 'Kontrollpunkt'],
            ['code' => 'tor', 'label' => 'Tor'],
            ['code' => 'tuer', 'label' => 'Tür'],
            ['code' => 'alarmanlage', 'label' => 'Alarmanlage'],
            ['code' => 'kamera', 'label' => 'Kamera'],
            ['code' => 'schluessel', 'label' => 'Schlüssel'],
            ['code' => 'ausweis', 'label' => 'Ausweis'],
            ['code' => 'fahrzeug', 'label' => 'Fahrzeug'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'revierfahrt',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'revierfahrt',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'alarm',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'alarm',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'vorfall',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'vorfall',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
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
            'entry_type_code' => 'uebergabe',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'SD_REVIERFAHRT',
            'name' => 'Revierfahrt mit Kontrollpunkten',
            'domain' => 'sicherheitsdienst',
            'risk_level' => 'normal',
            'description' => 'Geführte Revierfahrt: Tour, Kontrollpunkte und Zeiten nachweisbar.',
            'steps' => [
                ['code' => 'tour', 'step_type' => 'text', 'label' => 'Tour/Revier erfassen'],
                ['code' => 'kontrollpunkte', 'step_type' => 'confirm', 'label' => 'Kontrollpunkte nach Plan abfahren'],
                ['code' => 'befund', 'step_type' => 'photo', 'label' => 'Auffälligkeiten dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten'],
            ],
        ],
        [
            'code' => 'SD_ALARMVERFOLGUNG',
            'name' => 'Alarmverfolgung',
            'domain' => 'sicherheitsdienst',
            'risk_level' => 'high',
            'description' => 'Strukturierte Alarmverfolgung: Objekt, Alarmzeit, Maßnahme und Ergebnis.',
            'steps' => [
                ['code' => 'alarmzeit', 'step_type' => 'text', 'label' => 'Objekt und Alarmzeit erfassen'],
                ['code' => 'anfahrt', 'step_type' => 'confirm', 'label' => 'Anfahrt und Außenkontrolle durchführen'],
                ['code' => 'massnahme', 'step_type' => 'text', 'label' => 'Maßnahme dokumentieren'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Ergebnis festhalten (inkl. Fehlalarm)'],
            ],
        ],
        ['code' => 'SD_WACHBUCH'],
        ['code' => 'SD_KONTROLLGANG'],
        ['code' => 'SD_VORFALL'],
        ['code' => 'SD_SCHLUESSEL'],
        ['code' => 'SD_UEBERGABE'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'SD_WACHBUCH_EINTRAG'],
        ['code' => 'SD_REVIERBERICHT'],
        ['code' => 'SD_ALARMBERICHT'],
        ['code' => 'SD_VORFALLMELDUNG'],
        ['code' => 'SD_SCHLUESSELNACHWEIS'],
        ['code' => 'SD_UEBERGABE'],
    ],
    'asset_categories' => [
        'dienstfahrzeug',
        'funkgeraet',
        'taschenlampe',
        'bodycam',
        'schluesselkasten',
        'scanner',
        'diensthandy',
        'warnweste',
    ],
    'tags_seed' => [
        '#alarm',
        '#revier',
        '#wachbuch',
        '#vorfall',
        '#schluessel',
        '#polizei',
        '#kunde-informiert',
        '#fehlalarm',
    ],
    'maintenance_plans_seed' => [
        ['code' => 'SD-FUNK-12M', 'label' => 'Funkgerät Funktionsprüfung', 'category_code' => 'funkgeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 7],
        ['code' => 'SD-BODYCAM-12M', 'label' => 'Bodycam Prüfung/Datenlöschkonzept', 'category_code' => 'bodycam', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'SD-FAHRZEUG-12M', 'label' => 'Dienstfahrzeug UVV-Prüfung', 'category_code' => 'dienstfahrzeug', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
    ],
    'sla_contracts_seed' => [
        [
            'code' => 'SLA-SD-STANDARD',
            'label' => 'Sicherheitsdienst Standard-SLA',
            'is_default' => true,
            'priority_table' => [
                'low'    => ['reaction_minutes' => 720, 'resolution_minutes' => 4320],
                'normal' => ['reaction_minutes' => 240, 'resolution_minutes' => 1440],
                'high'   => ['reaction_minutes' => 60,  'resolution_minutes' => 480],
                'urgent' => ['reaction_minutes' => 15,  'resolution_minutes' => 240],
            ],
            // Rund-um-die-Uhr-Bereitschaft (Revier-/Alarmdienst).
            'business_hours' => [
                ['weekday' => 1, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 2, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 3, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 4, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 5, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 6, 'from' => '00:00', 'to' => '23:59'],
                ['weekday' => 7, 'from' => '00:00', 'to' => '23:59'],
            ],
        ],
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): personenbezogene
    // Beobachtungs-/Videodaten → GVV und DSFA standardmäßig AKTIV.
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required'],
        ['key' => 'dpia_required'],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],
];
