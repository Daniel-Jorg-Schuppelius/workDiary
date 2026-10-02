<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : hausmeister.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'hausmeister',
    'label' => 'Hausmeisterdienst',
    'description' => 'Hausmeisterdienst und Objektbetreuung: Kontrollgänge, Kleinreparaturen, Grünpflege, Winterdienst, Mülltonnen und Ablesungen mit Rundgangsprotokoll.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Rasenfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Wegfläche (Winterdienst)', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Stückzahl', 'formula' => '00', 'unit' => 'Stk', 'factor' => '1'],
        ],
    ],
    'modules_recommended' => [
        'module.planung',
        'module.documents',
        'module.forms',
        'module.liegenschaften',
        'module.fuhrpark',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'kontrollgang', 'label' => 'Kontrollgang'],
            ['code' => 'kleinreparatur', 'label' => 'Kleinreparatur'],
            ['code' => 'gruenpflege', 'label' => 'Grünpflege'],
            ['code' => 'winterdienst', 'label' => 'Winterdienst'],
            ['code' => 'muell', 'label' => 'Mülltonnendienst'],
            ['code' => 'ablesung', 'label' => 'Zählerablesung'],
        ],
        'activity' => [
            ['code' => 'pruefen', 'label' => 'Prüfen'],
            ['code' => 'reparieren', 'label' => 'Reparieren'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'streuen', 'label' => 'Räumen und streuen'],
            ['code' => 'ablesen', 'label' => 'Ablesen'],
            ['code' => 'melden', 'label' => 'Mangel melden'],
        ],
        'defect_type' => [
            ['code' => 'beleuchtung', 'label' => 'Beleuchtung defekt'],
            ['code' => 'tuer', 'label' => 'Tür/Schloss defekt'],
            ['code' => 'wasser', 'label' => 'Wasserschaden'],
            ['code' => 'verschmutzung', 'label' => 'Verschmutzung'],
            ['code' => 'sicherheit', 'label' => 'Sicherheitsmangel'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'vandalismus', 'label' => 'Vandalismus'],
            ['code' => 'witterung', 'label' => 'Witterung'],
            ['code' => 'nutzung', 'label' => 'Nutzung'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'fremdfirma', 'label' => 'An Fachfirma übergeben'],
            ['code' => 'verwaltungInformiert', 'label' => 'Verwaltung informiert'],
            ['code' => 'offen', 'label' => 'Offen'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'kontrollgang', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'HM_KONTROLLGANG',
            'name' => 'Kontrollgang',
            'name_i18n' => ['en' => 'Inspection round', 'es' => 'Ronda de control', 'fr' => 'Ronde de contrôle', 'it' => 'Giro di controllo'],
            'domain' => 'hausmeister',
            'risk_level' => 'normal',
            'description' => 'Kontrollgang mit festen Prüfpunkten, Mängelaufnahme und Fotos.',
            'steps' => [
                ['code' => 'treppenhaus', 'step_type' => 'confirm', 'label' => 'Treppenhaus, Beleuchtung und Türen prüfen'],
                ['code' => 'technik', 'step_type' => 'confirm', 'label' => 'Technikräume und Zähler prüfen'],
                ['code' => 'aussen', 'step_type' => 'confirm', 'label' => 'Außenanlagen und Müllplatz prüfen'],
                ['code' => 'maengel', 'step_type' => 'text', 'label' => 'Mängel festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Mängel fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'hm/glaette', 'category' => 'Ausrutschen', 'hazard' => 'Glätte beim Winterdienst', 'measure' => 'Rutschfeste Schuhe, früh streuen', 'severity' => 3, 'likelihood' => 4],
        ['code' => 'hm/leiter', 'category' => 'Absturz', 'hazard' => 'Absturz von Leitern beim Lampenwechsel', 'measure' => 'Leitern prüfen, nur kurz nutzen', 'severity' => 4, 'likelihood' => 3],
        ['code' => 'hm/allein', 'category' => 'Alleinarbeit', 'hazard' => 'Alleinarbeit in Technikräumen', 'measure' => 'Meldesystem, Erreichbarkeit', 'severity' => 3, 'likelihood' => 2],
    ],
    'tags_seed' => [
        '#kontrollgang',
        '#winterdienst',
        '#gruen',
        '#reparatur',
        '#ablesung',
    ],
];
