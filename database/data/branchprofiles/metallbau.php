<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : metallbau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'metallbau',
    'label' => 'Metallbau',
    'description' => 'Metallbau und Schlosserei: Geländer, Treppen, Tore, Stahlkonstruktionen und Fassaden, Schweißarbeiten, Montage und Wartung nach DIN EN 1090.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Laufende Meter Geländer', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
            ['label' => 'Fläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Stückzahl', 'formula' => '00', 'unit' => 'Stk', 'factor' => '1'],
            ['label' => 'Gewicht (Rechenansatz)', 'formula' => '91', 'unit' => 'kg', 'factor' => '1'],
        ],
    ],
    'modules_recommended' => [
        'module.planung',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.lager',
        'module.asset_compliance',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'gelaender', 'label' => 'Geländer'],
            ['code' => 'treppe', 'label' => 'Stahltreppe'],
            ['code' => 'tor', 'label' => 'Tor/Zaun'],
            ['code' => 'konstruktion', 'label' => 'Stahlkonstruktion'],
            ['code' => 'schweissen', 'label' => 'Schweißarbeiten'],
            ['code' => 'wartung', 'label' => 'Torwartung'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'fertigen', 'label' => 'Fertigen'],
            ['code' => 'schweissen', 'label' => 'Schweißen'],
            ['code' => 'beschichten', 'label' => 'Beschichten'],
            ['code' => 'montieren', 'label' => 'Montieren'],
            ['code' => 'pruefen', 'label' => 'Prüfen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'korrosion', 'label' => 'Korrosion'],
            ['code' => 'schweissnaht', 'label' => 'Schweißnaht mangelhaft'],
            ['code' => 'mass', 'label' => 'Maßabweichung'],
            ['code' => 'befestigung', 'label' => 'Befestigung lose'],
            ['code' => 'antrieb', 'label' => 'Antrieb defekt'],
        ],
        'root_cause' => [
            ['code' => 'witterung', 'label' => 'Witterung'],
            ['code' => 'ausfuehrung', 'label' => 'Ausführung'],
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
        ],
        'result' => [
            ['code' => 'fertig', 'label' => 'Fertig'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'ersatzteil', 'label' => 'Ersatzteil bestellt'],
            ['code' => 'stillgelegt', 'label' => 'Stillgelegt'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'gelaender', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'MB_TORPRUEFUNG',
            'name' => 'Torprüfung',
            'name_i18n' => ['en' => 'Gate inspection', 'es' => 'Inspección de portón', 'fr' => 'Contrôle de portail', 'it' => 'Verifica del cancello'],
            'domain' => 'metallbau',
            'risk_level' => 'normal',
            'description' => 'Prüfung kraftbetätigter Tore mit Sicherheitseinrichtungen und Kraftmessung.',
            'steps' => [
                ['code' => 'sicht', 'step_type' => 'confirm', 'label' => 'Sichtprüfung Tor und Antrieb'],
                ['code' => 'sicherheit', 'step_type' => 'confirm', 'label' => 'Sicherheitseinrichtungen prüfen'],
                ['code' => 'kraft', 'step_type' => 'messreihe', 'label' => 'Schließkräfte messen', 'requires_proof_type' => 'measure'],
                ['code' => 'befund', 'step_type' => 'text', 'label' => 'Befund festhalten'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'metall/schweissen', 'category' => 'Brand/Explosion', 'hazard' => 'Brand und Schweißrauch', 'measure' => 'Brandwache, Absaugung, Schweißerschutz', 'severity' => 4, 'likelihood' => 3],
        ['code' => 'metall/schnitt', 'category' => 'Mechanische Gefährdung', 'hazard' => 'Schnittverletzungen an Blechkanten', 'measure' => 'Schnittschutzhandschuhe', 'severity' => 3, 'likelihood' => 4],
        ['code' => 'metall/heben', 'category' => 'Physische Belastung', 'hazard' => 'Schwere Bauteile montieren', 'measure' => 'Hebezeuge, zu zweit montieren', 'severity' => 3, 'likelihood' => 3],
    ],
    'tags_seed' => [
        '#gelaender',
        '#tor',
        '#stahl',
        '#schweissen',
        '#wartung',
    ],
];
