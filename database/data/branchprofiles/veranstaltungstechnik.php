<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : veranstaltungstechnik.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'veranstaltungstechnik',
    'label' => 'Veranstaltungstechnik',
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
        'module.rental',
        'module.lager',
        'module.fuhrpark',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'angebot', 'label' => 'Angebot'],
            ['code' => 'vorbereitung', 'label' => 'Vorbereitung'],
            ['code' => 'anlieferung', 'label' => 'Anlieferung'],
            ['code' => 'aufbau', 'label' => 'Aufbau'],
            ['code' => 'safetyCheck', 'label' => 'Safety Check'],
            ['code' => 'soundcheck', 'label' => 'Soundcheck'],
            ['code' => 'showbetreuung', 'label' => 'Showbetreuung'],
            ['code' => 'abbau', 'label' => 'Abbau'],
            ['code' => 'ruecknahme', 'label' => 'Ruecknahme'],
            ['code' => 'schaden', 'label' => 'Schaden'],
        ],
        'activity' => [
            ['code' => 'planen', 'label' => 'Planen'],
            ['code' => 'laden', 'label' => 'Laden'],
            ['code' => 'verkabeln', 'label' => 'Verkabeln'],
            ['code' => 'riggen', 'label' => 'Riggen'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'programmieren', 'label' => 'Programmieren'],
            ['code' => 'testen', 'label' => 'Testen'],
            ['code' => 'betreuen', 'label' => 'Betreuen'],
            ['code' => 'abbauen', 'label' => 'Abbauen'],
            ['code' => 'inventarisieren', 'label' => 'Inventarisieren'],
        ],
        'defect_type' => [
            ['code' => 'equipmentFehlt', 'label' => 'Equipment fehlt'],
            ['code' => 'kabelDefekt', 'label' => 'Kabel defekt'],
            ['code' => 'stromproblem', 'label' => 'Stromproblem'],
            ['code' => 'riggingMangel', 'label' => 'Rigging-Mangel'],
            ['code' => 'transportschaden', 'label' => 'Transportschaden'],
            ['code' => 'kundenAenderung', 'label' => 'Kundenaenderung'],
            ['code' => 'zeitverzug', 'label' => 'Zeitverzug'],
        ],
        'root_cause' => [
            ['code' => 'planung', 'label' => 'Planung'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'fremdgewerk', 'label' => 'Fremdgewerk'],
            ['code' => 'location', 'label' => 'Location'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'verschleiss', 'label' => 'Verschleiss'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'nichtFreigegeben', 'label' => 'Nicht freigegeben'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'ersatzEquipment', 'label' => 'Ersatzequipment'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'ton', 'label' => 'Ton'],
            ['code' => 'licht', 'label' => 'Licht'],
            ['code' => 'video', 'label' => 'Video'],
            ['code' => 'buehne', 'label' => 'Buehne'],
            ['code' => 'rigging', 'label' => 'Rigging'],
            ['code' => 'strom', 'label' => 'Strom'],
            ['code' => 'truss', 'label' => 'Truss'],
            ['code' => 'mikrofon', 'label' => 'Mikrofon'],
            ['code' => 'lautsprecher', 'label' => 'Lautsprecher'],
            ['code' => 'mischpult', 'label' => 'Mischpult'],
        ],
        'dienstmittel_type' => [
            ['code' => 'stativ', 'label' => 'Stativ'],
            ['code' => 'stromverteiler', 'label' => 'Stromverteiler'],
            ['code' => 'kabelcase', 'label' => 'Kabelcase'],
            ['code' => 'videoprojektor', 'label' => 'Videoprojektor'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'aufbau',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'aufbau',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'safetyCheck',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'soundcheck',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'abbau',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'ruecknahme',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'schaden',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'schaden',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'VT_STROM_CHECK',
            'name' => 'Stromcheck / Elektrosicherheit',
            'domain' => 'veranstaltungstechnik',
            'risk_level' => 'critical',
            'description' => 'Prüfung der Stromversorgung mit Messung und Freigabe im Vier-Augen-Prinzip.',
            'steps' => [
                ['code' => 'verteiler', 'step_type' => 'confirm', 'label' => 'Verteiler/Einspeisung prüfen'],
                ['code' => 'rcd', 'step_type' => 'confirm', 'label' => 'RCD/FI-Test durchführen'],
                ['code' => 'messung', 'step_type' => 'messreihe', 'label' => 'Schutzleiter/Isolation messen'],
                ['code' => 'freigabe', 'step_type' => 'signature', 'label' => 'Stromfreigabe', 'requires_proof_type' => 'signature', 'requires_second_person' => true],
            ],
        ],
        [
            'code' => 'VT_RIGGING_CHECK',
            'name' => 'Rigging-Check',
            'domain' => 'veranstaltungstechnik',
            'risk_level' => 'critical',
            'description' => 'Lastprüfung und Sichtkontrolle des Riggings mit Freigabe im Vier-Augen-Prinzip.',
            'steps' => [
                ['code' => 'lastberechnung', 'step_type' => 'confirm', 'label' => 'Lastberechnung/Traglasten prüfen'],
                ['code' => 'anschlagmittel', 'step_type' => 'confirm', 'label' => 'Anschlagmittel/Traversen prüfen'],
                ['code' => 'sichtpruefung', 'step_type' => 'confirm', 'label' => 'Sichtprüfung Aufhängepunkte'],
                ['code' => 'freigabe', 'step_type' => 'signature', 'label' => 'Rigging-Freigabe', 'requires_proof_type' => 'signature', 'requires_second_person' => true],
            ],
        ],
        [
            'code' => 'VT_SOUNDCHECK',
            'name' => 'Soundcheck',
            'domain' => 'veranstaltungstechnik',
            'risk_level' => 'normal',
            'description' => 'Soundcheck inkl. Pegelmessung und Freigabe.',
            'steps' => [
                ['code' => 'aufbau', 'step_type' => 'confirm', 'label' => 'PA/Monitoring-Aufbau prüfen'],
                ['code' => 'pegel', 'step_type' => 'number', 'label' => 'Schalldruckpegel (dB) messen'],
                ['code' => 'check', 'step_type' => 'confirm', 'label' => 'Soundcheck durchführen'],
                ['code' => 'freigabe', 'step_type' => 'freigabe', 'label' => 'Für Show freigeben'],
            ],
        ],
        ['code' => 'VT_EVENT_PLANUNG'],
        ['code' => 'VT_AUFBAU'],
        ['code' => 'VT_SHOWBETREUUNG'],
        ['code' => 'VT_ABBAU'],
        ['code' => 'VT_SCHADEN'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'VT_EVENTBRIEFING'],
        ['code' => 'VT_EQUIPMENTLISTE'],
        ['code' => 'VT_SAFETY_CHECK'],
        ['code' => 'VT_SOUNDCHECK'],
        ['code' => 'VT_ABNAHME'],
        ['code' => 'VT_RUECKNAHME'],
        ['code' => 'VT_SCHADEN'],
    ],
    'asset_categories' => [
        'lautsprecher',
        'mischpult',
        'mikrofon',
        'scheinwerfer',
        'dimmer',
        'truss',
        'stativ',
        'stromverteiler',
        'kabelcase',
        'videoprojektor',
    ],
    'tags_seed' => [
        '#ton',
        '#licht',
        '#rigging',
        '#strom',
        '#safety',
        '#show',
        '#equipment',
        '#schaden',
    ],
];
