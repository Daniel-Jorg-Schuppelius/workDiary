<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'shk',
    'label' => 'SHK',
    'description' => 'Sanitär, Heizung, Klima: Wartung, Störung, Installation, Inbetriebnahme, Druck- und Dichtheitsprüfung mit Prüfplänen für Heizung und Trinkwasser.',
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
        'module.lager',
        'module.fuhrpark',
        'module.bau',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'stoerung', 'label' => 'Stoerung'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'inbetriebnahme', 'label' => 'Inbetriebnahme'],
            ['code' => 'druckpruefung', 'label' => 'Druckpruefung'],
            ['code' => 'dichtheitspruefung', 'label' => 'Dichtheitspruefung'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
            ['code' => 'notdienst', 'label' => 'Notdienst'],
        ],
        'activity' => [
            ['code' => 'entlueften', 'label' => 'Entlueften'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'tauschen', 'label' => 'Tauschen'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'abdichten', 'label' => 'Abdichten'],
            ['code' => 'einstellen', 'label' => 'Einstellen'],
            ['code' => 'spuelen', 'label' => 'Spuelen'],
            ['code' => 'pruefen', 'label' => 'Pruefen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'leckage', 'label' => 'Leckage'],
            ['code' => 'druckverlust', 'label' => 'Druckverlust'],
            ['code' => 'heizungsstoerung', 'label' => 'Heizungsstoerung'],
            ['code' => 'verstopfung', 'label' => 'Verstopfung'],
            ['code' => 'korrosion', 'label' => 'Korrosion'],
            ['code' => 'sensorDefekt', 'label' => 'Sensordefekt'],
            ['code' => 'brennerStoerung', 'label' => 'Brennerstoerung'],
            ['code' => 'geraeusch', 'label' => 'Geraeusch'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiss'],
            ['code' => 'verkalkung', 'label' => 'Verkalkung'],
            ['code' => 'frost', 'label' => 'Frost'],
            ['code' => 'montagefehler', 'label' => 'Montagefehler'],
            ['code' => 'nutzung', 'label' => 'Nutzung'],
            ['code' => 'materialfehler', 'label' => 'Materialfehler'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
        ],
        'result' => [
            ['code' => 'behoben', 'label' => 'Behoben'],
            ['code' => 'teilBehoben', 'label' => 'Teilweise behoben'],
            ['code' => 'dicht', 'label' => 'Dicht'],
            ['code' => 'nichtDicht', 'label' => 'Nicht dicht'],
            ['code' => 'ersatzteilNoetig', 'label' => 'Ersatzteil noetig'],
            ['code' => 'kundenentscheidung', 'label' => 'Kundenentscheidung'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'heizung', 'label' => 'Heizung'],
            ['code' => 'therme', 'label' => 'Therme'],
            ['code' => 'boiler', 'label' => 'Boiler'],
            ['code' => 'pumpe', 'label' => 'Pumpe'],
            ['code' => 'ventil', 'label' => 'Ventil'],
            ['code' => 'rohrleitung', 'label' => 'Rohrleitung'],
            ['code' => 'heizkoerper', 'label' => 'Heizkoerper'],
            ['code' => 'sanitaerObjekt', 'label' => 'Sanitaerobjekt'],
            ['code' => 'klimaGeraet', 'label' => 'Klimageraet'],
            ['code' => 'lueftung', 'label' => 'Lueftung'],
        ],
        // Genehmigungsarten (Auswahl/Filter je Eintrag). Status, Frist und
        // Nachweis werden im Genehmigungs-Register (Permit) geführt.
        'permit_type' => [
            ['code' => 'schornsteinfeger', 'label' => 'Schornsteinfeger-Abnahme'],
            ['code' => 'gasanmeldung', 'label' => 'Gas-Anmeldung (Netzbetreiber)'],
            ['code' => 'wasserrecht', 'label' => 'Wasserrechtliche Erlaubnis'],
            ['code' => 'emissionsschutz', 'label' => 'Emissionsschutz (BImSchV)'],
            ['code' => 'dichtheitsnachweis', 'label' => 'Dichtheitsnachweis'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'inbetriebnahme',
            'required_domain' => 'permit_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
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
            'entry_type_code' => 'druckpruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'dichtheitspruefung',
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
            'severity' => ClassificationRequirementSeverity::Hard->value,
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
            'entry_type_code' => 'inbetriebnahme',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'inbetriebnahme',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'SHK_WARTUNG',
            'name' => 'Anlagenwartung (Heizung/Sanitär)',
            'domain' => 'shk',
            'risk_level' => 'normal',
            'description' => 'Wiederkehrende Wartung mit Anlagenakte und Kundenabnahme.',
            'steps' => [
                ['code' => 'anlagedaten', 'step_type' => 'confirm', 'label' => 'Anlagendaten/Seriennummer prüfen'],
                ['code' => 'sichtpruefung', 'step_type' => 'confirm', 'label' => 'Sicht- und Funktionsprüfung'],
                ['code' => 'verschleissteile', 'step_type' => 'material', 'label' => 'Verschleißteile prüfen/erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'messwerte', 'step_type' => 'messreihe', 'label' => 'Betriebswerte erfassen', 'requires_proof_type' => 'measure'],
                ['code' => 'wartungsprotokoll', 'step_type' => 'link_protocol', 'label' => 'Wartungsprotokoll verknüpfen', 'required' => false, 'blocking' => false],
                ['code' => 'kundenabnahme', 'step_type' => 'signature', 'label' => 'Kundenabnahme', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'SHK_DRUCKPRUEFUNG',
            'name' => 'Druck- und Dichtheitsprüfung',
            'domain' => 'shk',
            'risk_level' => 'high',
            'description' => 'Druckprüfung mit dokumentierten Messwerten vor Inbetriebnahme.',
            'steps' => [
                ['code' => 'absperren', 'step_type' => 'confirm', 'label' => 'Anlage absperren/vorbereiten'],
                ['code' => 'pruefdruck', 'step_type' => 'number', 'label' => 'Prüfdruck anlegen', 'requires_proof_type' => 'measure'],
                ['code' => 'haltezeit', 'step_type' => 'confirm', 'label' => 'Haltezeit einhalten'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Dichtheit bewerten'],
                ['code' => 'protokoll', 'step_type' => 'link_protocol', 'label' => 'Druckprüfprotokoll verknüpfen', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'SHK_STOERUNG',
            'name' => 'Störungseinsatz Heizung/Sanitär',
            'domain' => 'shk',
            'risk_level' => 'normal',
            'description' => 'Störungsbehebung mit Diagnose, Absperrung, Instandsetzung und Funktionsprüfung.',
            'steps' => [
                ['code' => 'symptom', 'step_type' => 'text', 'label' => 'Störungsbild aufnehmen'],
                ['code' => 'diagnose', 'step_type' => 'choice', 'label' => 'Ursache einstufen'],
                ['code' => 'absperren', 'step_type' => 'confirm', 'label' => 'Anlage absperren/drucklos machen', 'required' => false, 'blocking' => false],
                ['code' => 'behebung', 'step_type' => 'confirm', 'label' => 'Störung beheben'],
                ['code' => 'material', 'step_type' => 'material', 'label' => 'Verbrauchtes Material erfassen', 'required' => false, 'blocking' => false],
                ['code' => 'messwerte', 'step_type' => 'messreihe', 'label' => 'Betriebswerte nach Instandsetzung', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'measure'],
                ['code' => 'funktion', 'step_type' => 'confirm', 'label' => 'Funktionsprüfung durchführen'],
            ],
        ],
        [
            'code' => 'SHK_DICHTHEIT',
            'name' => 'Dichtheitsprüfung Gas/Wasser',
            'domain' => 'shk',
            'risk_level' => 'high',
            'description' => 'Dichtheitsprüfung mit Prüfmedium, Prüfdruck, Haltezeit, Bewertung und Protokoll.',
            'steps' => [
                ['code' => 'absperren', 'step_type' => 'confirm', 'label' => 'Prüfabschnitt absperren und vorbereiten'],
                ['code' => 'pruefmedium', 'step_type' => 'choice', 'label' => 'Prüfmedium/Verfahren (Luft, Wasser, Inertgas)'],
                ['code' => 'druck', 'step_type' => 'number', 'label' => 'Prüfdruck (bar)', 'requires_proof_type' => 'measure'],
                ['code' => 'haltezeit', 'step_type' => 'number', 'label' => 'Haltezeit (Minuten)'],
                ['code' => 'ergebnis', 'step_type' => 'choice', 'label' => 'Dichtheit bewerten'],
                ['code' => 'protokoll', 'step_type' => 'link_protocol', 'label' => 'Prüfprotokoll verknüpfen', 'required' => false, 'blocking' => false],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Manometer/Prüfaufbau dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'SHK_INBETRIEBNAHME',
            'name' => 'Inbetriebnahme Anlage',
            'domain' => 'shk',
            'risk_level' => 'high',
            'description' => 'Inbetriebnahme mit Befüllen, Einstellung, Abgas-/Betriebswerten, Sicherheitseinrichtungen und Abnahme.',
            'steps' => [
                ['code' => 'befuellen', 'step_type' => 'confirm', 'label' => 'Anlage befüllen und entlüften'],
                ['code' => 'einstellung', 'step_type' => 'confirm', 'label' => 'Parameter und hydraulischen Abgleich einstellen'],
                ['code' => 'abgas', 'step_type' => 'messreihe', 'label' => 'Abgas-/Betriebswerte messen', 'requires_proof_type' => 'measure'],
                ['code' => 'sicherheit', 'step_type' => 'confirm', 'label' => 'Sicherheitseinrichtungen prüfen'],
                ['code' => 'einweisung', 'step_type' => 'confirm', 'label' => 'Betreiber einweisen und Unterlagen übergeben'],
                ['code' => 'abnahme', 'step_type' => 'signature', 'label' => 'Abnahme durch den Betreiber', 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'SHK_NOTDIENST',
            'name' => 'Notdiensteinsatz',
            'domain' => 'shk',
            'risk_level' => 'high',
            'description' => 'Notdienst mit Gefährdungseinstufung, Gefahrenabwehr, Provisorium und Folgeauftrag.',
            'steps' => [
                ['code' => 'gefahr', 'step_type' => 'choice', 'label' => 'Gefährdung einstufen (Gas, Wasser, Heizungsausfall)'],
                ['code' => 'sicherung', 'step_type' => 'confirm', 'label' => 'Gefahr abstellen (absperren, abschalten)'],
                ['code' => 'provisorium', 'step_type' => 'confirm', 'label' => 'Provisorium/Notlösung hergestellt'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Schadensbild dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'folgeauftrag', 'step_type' => 'confirm', 'label' => 'Folgeauftrag zur Instandsetzung angelegt'],
                ['code' => 'unterschrift', 'step_type' => 'signature', 'label' => 'Einsatz bestätigen lassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
    ],
    'maintenance_plans_seed' => [
        ['code' => 'SHK-HEIZUNG-12M', 'label' => 'Heizungswartung jährlich', 'category_code' => 'heizung', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'SHK-THERME-12M', 'label' => 'Therme / Brennwertgerät Wartung', 'category_code' => 'therme', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'SHK-TRINKWASSER-12M', 'label' => 'Trinkwasser-/Legionellenprüfung', 'category_code' => 'trinkwasser', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'SHK-MESSGERAET-12M', 'label' => 'Abgas-/Druckmessgerät Kalibrierung', 'category_code' => 'messgeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'shk_anlagenakte', 'kind' => 'operatorDuty', 'label' => 'Anlagenakte / Wartungsintervall', 'note' => 'Heizungs-/Sanitäranlage mit Wartungsintervall führen.'],
        ['code' => 'shk_pruefung', 'kind' => 'technicalInspection', 'label' => 'Wiederkehrende Prüfung (z. B. Trinkwasser)', 'level' => 'jährlich'],
        ['code' => 'shk_technikraum', 'kind' => 'accessRestriction', 'label' => 'Technikraum zutrittsbeschränkt', 'level' => 'fachkraft'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'SHK_WARTUNGSPROTOKOLL'],
        ['code' => 'SHK_DRUCKPROTOKOLL'],
        ['code' => 'SHK_DICHTHEITSPROTOKOLL'],
        ['code' => 'SHK_ABNAHME'],
        ['code' => 'SHK_ANLAGENAKTE'],
        ['code' => 'SHK_SERVICEBERICHT'],
    ],
    'asset_categories' => [
        'servicefahrzeug',
        'pressmaschine',
        'rohrkamera',
        'lecksuchgeraet',
        'druckpruefpumpe',
        'messgeraetAbgas',
        'leiter',
        'werkzeugkoffer',
    ],
    'tags_seed' => [
        '#notdienst',
        '#leckage',
        '#wartung',
        '#druckpruefung',
        '#ersatzteil',
        '#kundenabnahme',
        '#anlage',
    ],
    // Qualifikationen/Unterweisungen je Gewerk (Vollaudit 2026-07, N13).
    'qualifications_seed' => [
        ['name' => 'Gasgeräte-Sachkunde', 'abbreviation' => 'Gas-SK', 'description' => 'Sachkunde für Arbeiten an Gasgeräten (TRGI).'],
        ['name' => 'Trinkwasserhygiene-Schulung (VDI 6023)', 'abbreviation' => 'VDI 6023', 'description' => 'Hygieneschulung Kategorie B für Trinkwasserinstallationen.'],
        ['name' => 'Kälteschein Kategorie I', 'abbreviation' => 'KälteS', 'description' => 'Sachkundebescheinigung nach ChemKlimaschutzV für Arbeiten an Wärmepumpen/Klimaanlagen.'],
    ],
    // Vertragsvorlagen (MVP-893): Laufzeit, Kündigung, Pflichten relativ zum Beginn.
    'contract_templates' => [
        [
            'name' => 'Wartungsvertrag Heizung',
            'kind' => 'maintenance',
            'title' => 'Wartungsvertrag Heizungsanlage',
            'term_kind' => 'fixed',
            'min_term_months' => 12,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 90,
            'value_period' => 'yearly',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Heizungswartung', 'offset_months' => 8, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
    ],
];
