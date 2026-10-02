<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : dachdecker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'dachdecker',
    'label' => 'Dachdecker',
    'description' => 'Dachdecker: Steil- und Flachdach, Dachrinnen und Klempnerarbeiten, Dachfenster, Wartung und Sturmschäden mit Dachflächenaufmaß und Absturzsicherung.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Dachfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Dreiecksfläche (Walm)', 'formula' => '01', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Trapezfläche', 'formula' => '05', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Dachfenster abziehen', 'formula' => '04', 'unit' => 'm2', 'factor' => '-1'],
            ['label' => 'Rinne/Traufe', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
        ],
    ],
    'modules_recommended' => [
        'module.planung',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.lager',
        'module.fuhrpark',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'steildach', 'label' => 'Steildach'],
            ['code' => 'flachdach', 'label' => 'Flachdach'],
            ['code' => 'rinne', 'label' => 'Dachrinne/Klempnerei'],
            ['code' => 'dachfenster', 'label' => 'Dachfenster'],
            ['code' => 'wartung', 'label' => 'Dachwartung'],
            ['code' => 'sturmschaden', 'label' => 'Sturmschaden'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'eindecken', 'label' => 'Eindecken'],
            ['code' => 'abdichten', 'label' => 'Abdichten'],
            ['code' => 'daemmen', 'label' => 'Dämmen'],
            ['code' => 'reparieren', 'label' => 'Reparieren'],
            ['code' => 'sichern', 'label' => 'Absturzsicherung herstellen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'undicht', 'label' => 'Undichtigkeit'],
            ['code' => 'ziegelbruch', 'label' => 'Ziegelbruch'],
            ['code' => 'rinneDefekt', 'label' => 'Rinne defekt'],
            ['code' => 'anschluss', 'label' => 'Anschluss mangelhaft'],
            ['code' => 'feuchte', 'label' => 'Feuchtigkeit im Dachraum'],
        ],
        'root_cause' => [
            ['code' => 'sturm', 'label' => 'Sturm/Unwetter'],
            ['code' => 'alterung', 'label' => 'Alterung'],
            ['code' => 'ausfuehrung', 'label' => 'Ausführung'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
        ],
        'result' => [
            ['code' => 'dicht', 'label' => 'Dicht'],
            ['code' => 'provisorisch', 'label' => 'Provisorisch gesichert'],
            ['code' => 'folgeauftrag', 'label' => 'Folgeauftrag nötig'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'steildach', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'DD_DACHPRUEFUNG',
            'name' => 'Dachinspektion',
            'name_i18n' => ['en' => 'Roof inspection', 'es' => 'Inspección de cubierta', 'fr' => 'Inspection de toiture', 'it' => 'Ispezione del tetto'],
            'domain' => 'dachdecker',
            'risk_level' => 'normal',
            'description' => 'Dachinspektion mit Sicherung, Sichtprüfung, Befund und Fotos.',
            'steps' => [
                ['code' => 'sicherung', 'step_type' => 'confirm', 'label' => 'Absturzsicherung herstellen'],
                ['code' => 'sicht', 'step_type' => 'confirm', 'label' => 'Dachfläche, Anschlüsse und Rinnen prüfen'],
                ['code' => 'befund', 'step_type' => 'text', 'label' => 'Befund festhalten'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Befund fotografieren', 'requires_proof_type' => 'photo'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'dach/absturz', 'category' => 'Absturz', 'hazard' => 'Absturz von Dachflächen und Dachkanten', 'measure' => 'Seitenschutz, Auffanggurt, Anschlagpunkte', 'severity' => 5, 'likelihood' => 3],
        ['code' => 'dach/witterung', 'category' => 'Witterung', 'hazard' => 'Glätte, Wind und Hitze auf dem Dach', 'measure' => 'Arbeiten bei Sturm und Glätte einstellen', 'severity' => 4, 'likelihood' => 3],
        ['code' => 'dach/lasten', 'category' => 'Physische Belastung', 'hazard' => 'Material auf das Dach heben', 'measure' => 'Aufzug/Kran nutzen', 'severity' => 3, 'likelihood' => 3],
    ],
    'tags_seed' => [
        '#steildach',
        '#flachdach',
        '#rinne',
        '#sturm',
        '#wartung',
    ],
];
