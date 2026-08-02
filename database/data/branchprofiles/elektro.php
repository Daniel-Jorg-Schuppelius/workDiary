<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : elektro.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'elektro',
    'label' => 'Elektro',
    // v2 (Feature 100): Entsorgungs-Modul empfohlen + AVV-Presets für
    // Kabel/Batterien (Altgeräte-Mitnahme von der Baustelle/beim Kunden).
    'version' => 2,
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
        'module.asset_compliance',
        'module.bau',
        'module.entsorgung',
    ],
    'classifications' => [
        // Feature 100: Elektro-typische Ergänzungen zu den AVV-Plattform-Defaults.
        'waste_code' => [
            ['code' => 'avv_170410_h', 'label' => '17 04 10* — Kabel mit Öl, Kohlenteer oder anderen gefährlichen Stoffen'],
            ['code' => 'avv_170411', 'label' => '17 04 11 — Kabel (nicht gefährlich)'],
            ['code' => 'avv_200133_h', 'label' => '20 01 33* — Gemischte Batterien (mit gefährlichen)'],
            ['code' => 'avv_200134', 'label' => '20 01 34 — Batterien (nicht gefährlich)'],
        ],
        'entry_type' => [
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'stoerung', 'label' => 'Stoerung'],
            ['code' => 'pruefung', 'label' => 'Pruefung'],
            ['code' => 'messung', 'label' => 'Messung'],
            ['code' => 'verteilerarbeit', 'label' => 'Verteilerarbeit'],
            ['code' => 'eCheck', 'label' => 'E-Check'],
            ['code' => 'wallbox', 'label' => 'Wallbox'],
            ['code' => 'pvAnschluss', 'label' => 'PV-Anschluss'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
        ],
        'activity' => [
            ['code' => 'freischalten', 'label' => 'Freischalten'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'anschliessen', 'label' => 'Anschliessen'],
            ['code' => 'verdrahten', 'label' => 'Verdrahten'],
            ['code' => 'beschriften', 'label' => 'Beschriften'],
            ['code' => 'pruefen', 'label' => 'Pruefen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'fehlersuche', 'label' => 'Fehlersuche'],
            ['code' => 'inbetriebnehmen', 'label' => 'Inbetriebnehmen'],
        ],
        'defect_type' => [
            ['code' => 'kurzschluss', 'label' => 'Kurzschluss'],
            ['code' => 'erdschluss', 'label' => 'Erdschluss'],
            ['code' => 'ueberlast', 'label' => 'Ueberlast'],
            ['code' => 'defekteSicherung', 'label' => 'Defekte Sicherung'],
            ['code' => 'loseKlemme', 'label' => 'Lose Klemme'],
            ['code' => 'isolationsfehler', 'label' => 'Isolationsfehler'],
            ['code' => 'falscheBeschriftung', 'label' => 'Falsche Beschriftung'],
            ['code' => 'messwertAbweichung', 'label' => 'Messwertabweichung'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiss'],
            ['code' => 'feuchtigkeit', 'label' => 'Feuchtigkeit'],
            ['code' => 'installation', 'label' => 'Installationsfehler'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'fremdgewerk', 'label' => 'Fremdgewerk'],
            ['code' => 'materialfehler', 'label' => 'Materialfehler'],
            ['code' => 'planungsfehler', 'label' => 'Planungsfehler'],
        ],
        'result' => [
            ['code' => 'behoben', 'label' => 'Behoben'],
            ['code' => 'teilBehoben', 'label' => 'Teilweise behoben'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'nichtFreigegeben', 'label' => 'Nicht freigegeben'],
            ['code' => 'nacharbeitNoetig', 'label' => 'Nacharbeit noetig'],
            ['code' => 'materialFehlt', 'label' => 'Material fehlt'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'verteiler', 'label' => 'Verteiler'],
            ['code' => 'sicherung', 'label' => 'Sicherung'],
            ['code' => 'leitung', 'label' => 'Leitung'],
            ['code' => 'steckdose', 'label' => 'Steckdose'],
            ['code' => 'beleuchtung', 'label' => 'Beleuchtung'],
            ['code' => 'wallbox', 'label' => 'Wallbox'],
            ['code' => 'pvWechselrichter', 'label' => 'PV-Wechselrichter'],
            ['code' => 'zaehlerplatz', 'label' => 'Zaehlerplatz'],
            ['code' => 'netzwerk', 'label' => 'Netzwerk'],
            ['code' => 'smartHome', 'label' => 'Smart Home'],
        ],
        // Genehmigungsarten (Auswahl/Filter je Eintrag). Status, Frist und
        // Nachweis werden im Genehmigungs-Register (Permit) geführt.
        'permit_type' => [
            ['code' => 'netzanmeldung', 'label' => 'Netzanmeldung (VNB)'],
            ['code' => 'zaehlersetzung', 'label' => 'Zählersetzung'],
            ['code' => 'anlagenanmeldung_pv', 'label' => 'PV-Anlagenanmeldung'],
            ['code' => 'e_anmeldung', 'label' => 'E-Anmeldung / TAB'],
            ['code' => 'abnahme_vnb', 'label' => 'Abnahme Netzbetreiber'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'pvAnschluss',
            'required_domain' => 'permit_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'wallbox',
            'required_domain' => 'permit_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => true,
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
            'entry_type_code' => 'installation',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'installation',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'eCheck',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'wallbox',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pvAnschluss',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'EL_SICHERHEITSCHECK',
            'name' => 'Sicherheitscheck Elektro (5 Sicherheitsregeln)',
            'domain' => 'elektro',
            'risk_level' => 'high',
            'description' => 'Arbeitsschutz-Vorabprüfung vor Arbeiten an elektrischen Anlagen.',
            'steps' => [
                ['code' => 'freischalten', 'step_type' => 'confirm', 'label' => 'Freischalten', 'description' => 'Anlage allpolig vom Netz trennen.'],
                ['code' => 'gegenWiedereinschaltenSichern', 'step_type' => 'confirm', 'label' => 'Gegen Wiedereinschalten sichern'],
                ['code' => 'spannungsfreiheit', 'step_type' => 'confirm', 'label' => 'Spannungsfreiheit feststellen', 'requires_second_person' => true],
                ['code' => 'erdenKurzschliessen', 'step_type' => 'confirm', 'label' => 'Erden und kurzschließen'],
                ['code' => 'benachbarteTeile', 'step_type' => 'confirm', 'label' => 'Benachbarte unter Spannung stehende Teile abdecken/abschranken'],
                ['code' => 'fotoArbeitsstelle', 'step_type' => 'photo', 'label' => 'Foto der gesicherten Arbeitsstelle', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'EL_ECHECK',
            'name' => 'E-Check / Prüfung ortsfester Anlagen',
            'domain' => 'elektro',
            'risk_level' => 'normal',
            'description' => 'Wiederkehrende Prüfung mit Messwerterfassung und Protokoll.',
            'steps' => [
                ['code' => 'sichtpruefung', 'step_type' => 'confirm', 'label' => 'Sichtprüfung durchführen'],
                ['code' => 'messwerte', 'step_type' => 'messreihe', 'label' => 'Messwerte erfassen', 'requires_proof_type' => 'measure'],
                ['code' => 'bewertung', 'step_type' => 'choice', 'label' => 'Prüfergebnis bewerten'],
                ['code' => 'protokoll', 'step_type' => 'link_protocol', 'label' => 'Prüfprotokoll verknüpfen', 'required' => false, 'blocking' => false],
            ],
        ],
        ['code' => 'EL_STOERUNG'],
        ['code' => 'EL_INSTALLATION'],
        ['code' => 'EL_VERTEILER'],
        ['code' => 'EL_WALLBOX'],
        ['code' => 'EL_INBETRIEBNAHME'],
    ],
    'maintenance_plans_seed' => [
        ['code' => 'EL-DGUVV3-GERAETE-12M', 'label' => 'DGUV V3 Prüfung ortsveränderlicher Geräte (jährlich)', 'category_code' => 'geraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'EL-DGUVV3-ANLAGE-48M', 'label' => 'DGUV V3 Prüfung ortsfester Anlagen (4 Jahre)', 'category_code' => 'anlage', 'interval_kind' => 'months', 'interval_value' => 48, 'tolerance_days' => 60],
        ['code' => 'EL-MESSGERAET-12M', 'label' => 'Messgeräte-Kalibrierung', 'category_code' => 'messgeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'EL-LEITER-12M', 'label' => 'Leiter-/Tritt-Prüfung (DGUV)', 'category_code' => 'leiter', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'el_pruefintervall', 'kind' => 'technicalInspection', 'label' => 'Wiederkehrende Prüfung (DGUV V3)', 'level' => 'jährlich', 'note' => 'Ortsfeste elektrische Anlagen im Raum wiederkehrend prüfen.'],
        ['code' => 'el_betreiberpflicht', 'kind' => 'operatorDuty', 'label' => 'Betreiberpflicht Elektroinstallation', 'note' => 'Verteiler/Unterverteilung zugänglich halten und dokumentieren.'],
        ['code' => 'el_zutritt', 'kind' => 'accessRestriction', 'label' => 'Zutritt nur für Elektrofachkraft', 'level' => 'fachkraft', 'note' => 'Schalt-/Verteilerräume zutrittsbeschränkt.'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'EL_PRUEFPROTOKOLL'],
        ['code' => 'EL_MESSPROTOKOLL'],
        ['code' => 'EL_SERVICEBERICHT'],
        ['code' => 'EL_ABNAHME'],
        ['code' => 'EL_VERTEILER_DOKU'],
        ['code' => 'EL_WALLBOX_INBETRIEBNAHME'],
    ],
    'asset_categories' => [
        'multimeter',
        'installationstester',
        'leiter',
        'servicefahrzeug',
        'crimpzange',
        'waermebildkamera',
        'labeldrucker',
        'bohrmaschine',
        'messadapter',
        'psaElektro',
    ],
    'tags_seed' => [
        '#vde',
        '#freischaltung',
        '#messwerte',
        '#verteiler',
        '#wallbox',
        '#stoerung',
        '#nacharbeit',
        '#kundenabnahme',
    ],
    // Qualifikationen/Unterweisungen je Gewerk (Vollaudit 2026-07, N13).
    'qualifications_seed' => [
        ['name' => 'Elektrofachkraft (EFK)', 'abbreviation' => 'EFK', 'description' => 'Ausgebildete Elektrofachkraft nach DIN VDE 1000-10.'],
        ['name' => 'DGUV Vorschrift 3 — Unterweisung', 'abbreviation' => 'DGUV V3', 'description' => 'Jährliche Unterweisung zum sicheren Arbeiten an elektrischen Anlagen.'],
        ['name' => 'Schaltberechtigung bis 30 kV', 'abbreviation' => 'SchaltB', 'description' => 'Benannte Schaltberechtigung für Mittelspannungsanlagen (befristet, auffrischungspflichtig).'],
        ['name' => 'Arbeiten unter Spannung (AuS)', 'abbreviation' => 'AuS', 'description' => 'Qualifikation für Arbeiten unter Spannung nach DIN VDE 0105-100.'],
    ],
];
