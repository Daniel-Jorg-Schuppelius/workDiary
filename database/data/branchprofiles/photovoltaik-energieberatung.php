<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : photovoltaik-energieberatung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'photovoltaik-energieberatung',
    'label' => 'Photovoltaik und Energieberatung',
    'description' => 'Photovoltaik, Speicher und Wallbox sowie Energieberatung: Vor-Ort-Begehung, Planung, Montage, Inbetriebnahme und Wartung mit Mess- und Übergabeprotokoll.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Dachfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Modulanzahl', 'formula' => '00', 'unit' => 'Stk', 'factor' => '1'],
            ['label' => 'Kabellänge', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
            ['label' => 'Mittelwert Messreihe', 'formula' => '31', 'unit' => 'V', 'factor' => '1'],
        ],
    ],
    'modules_recommended' => [
        'module.planung',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.lager',
        'module.sustainability',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'begehung', 'label' => 'Vor-Ort-Begehung'],
            ['code' => 'montage', 'label' => 'PV-Montage'],
            ['code' => 'speicher', 'label' => 'Speicher/Wallbox'],
            ['code' => 'inbetriebnahme', 'label' => 'Inbetriebnahme'],
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'beratung', 'label' => 'Energieberatung'],
        ],
        'activity' => [
            ['code' => 'planen', 'label' => 'Planen'],
            ['code' => 'montieren', 'label' => 'Montieren'],
            ['code' => 'verkabeln', 'label' => 'Verkabeln'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'anmelden', 'label' => 'Netzbetreiber anmelden'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'ertrag', 'label' => 'Minderertrag'],
            ['code' => 'wechselrichter', 'label' => 'Wechselrichter-Störung'],
            ['code' => 'isolation', 'label' => 'Isolationsfehler'],
            ['code' => 'verschattung', 'label' => 'Verschattung'],
            ['code' => 'kommunikation', 'label' => 'Kommunikationsfehler'],
        ],
        'root_cause' => [
            ['code' => 'verschmutzung', 'label' => 'Verschmutzung'],
            ['code' => 'defekt', 'label' => 'Defekt'],
            ['code' => 'planung', 'label' => 'Planung'],
            ['code' => 'netz', 'label' => 'Netzseite'],
        ],
        'result' => [
            ['code' => 'inBetrieb', 'label' => 'In Betrieb'],
            ['code' => 'eingeschraenkt', 'label' => 'Eingeschränkt in Betrieb'],
            ['code' => 'ersatzteil', 'label' => 'Ersatzteil bestellt'],
            ['code' => 'netzbetreiber', 'label' => 'Wartet auf Netzbetreiber'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'begehung', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'PV_INBETRIEBNAHME',
            'name' => 'PV-Inbetriebnahme',
            'name_i18n' => ['en' => 'PV commissioning', 'es' => 'Puesta en marcha fotovoltaica', 'fr' => 'Mise en service PV', 'it' => 'Messa in servizio FV'],
            'domain' => 'photovoltaik-energieberatung',
            'risk_level' => 'normal',
            'description' => 'Inbetriebnahme mit Sichtprüfung, Messungen nach VDE 0126-23, Einweisung und Übergabe.',
            'steps' => [
                ['code' => 'sicht', 'step_type' => 'confirm', 'label' => 'Sichtprüfung Generator und Verkabelung'],
                ['code' => 'messung', 'step_type' => 'messreihe', 'label' => 'Leerlaufspannung und Isolationswiderstand messen', 'requires_proof_type' => 'measure'],
                ['code' => 'einstellung', 'step_type' => 'confirm', 'label' => 'Wechselrichter und Monitoring einrichten'],
                ['code' => 'einweisung', 'step_type' => 'signature', 'label' => 'Einweisung bestätigen lassen', 'requires_proof_type' => 'signature'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'pv/absturz', 'category' => 'Absturz', 'hazard' => 'Absturz bei Dachmontage', 'measure' => 'Seitenschutz, Auffanggurt', 'severity' => 5, 'likelihood' => 3],
        ['code' => 'pv/strom', 'category' => 'Elektrische Gefährdung', 'hazard' => 'Gleichspannung am Generator', 'measure' => 'Freischalten, DC-Schutz, Fachkraft', 'severity' => 5, 'likelihood' => 2],
        ['code' => 'pv/heben', 'category' => 'Physische Belastung', 'hazard' => 'Module tragen', 'measure' => 'Zu zweit tragen, Aufzug', 'severity' => 3, 'likelihood' => 3],
    ],
    'tags_seed' => [
        '#pv',
        '#speicher',
        '#wallbox',
        '#energieberatung',
        '#wartung',
    ],
];
