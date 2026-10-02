<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : fliesen-bodenleger.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'fliesen-bodenleger',
    'label' => 'Fliesen- und Bodenleger',
    'description' => 'Fliesen-, Platten-, Mosaik- und Bodenleger: Bad und Küche, Bodenbeläge, Estrich, Abdichtung und Sockel mit Aufmaß nach Fläche und Abzug von Öffnungen.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Bodenfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Wandfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Öffnung abziehen', 'formula' => '04', 'unit' => 'm2', 'factor' => '-1'],
            ['label' => 'Sockel', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
            ['label' => 'Fläche aus Koordinaten (freie Formel)', 'formula' => '91', 'unit' => 'm2', 'factor' => '1'],
        ],
    ],
    'modules_recommended' => [
        'module.planung',
        'module.vertrieb',
        'module.documents',
        'module.forms',
        'module.lager',
    ],
    'classifications' => [
        'entry_type' => [
            ['code' => 'bad', 'label' => 'Badsanierung'],
            ['code' => 'boden', 'label' => 'Bodenbelag'],
            ['code' => 'wand', 'label' => 'Wandfliesen'],
            ['code' => 'estrich', 'label' => 'Estrich'],
            ['code' => 'abdichtung', 'label' => 'Abdichtung'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'ausbauen', 'label' => 'Ausbauen'],
            ['code' => 'ausgleichen', 'label' => 'Ausgleichen'],
            ['code' => 'abdichten', 'label' => 'Abdichten'],
            ['code' => 'verlegen', 'label' => 'Verlegen'],
            ['code' => 'verfugen', 'label' => 'Verfugen'],
            ['code' => 'silikon', 'label' => 'Silikonfugen ziehen'],
        ],
        'defect_type' => [
            ['code' => 'hohlstelle', 'label' => 'Hohlstelle'],
            ['code' => 'riss', 'label' => 'Riss'],
            ['code' => 'fuge', 'label' => 'Fuge defekt'],
            ['code' => 'gefaelle', 'label' => 'Gefälle falsch'],
            ['code' => 'feuchte', 'label' => 'Feuchteschaden'],
        ],
        'root_cause' => [
            ['code' => 'untergrund', 'label' => 'Untergrund'],
            ['code' => 'ausfuehrung', 'label' => 'Ausführung'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'bewegung', 'label' => 'Bauteilbewegung'],
        ],
        'result' => [
            ['code' => 'fertig', 'label' => 'Fertig'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'trocknung', 'label' => 'Trocknung abwarten'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'bad', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'FB_VERLEGUNG',
            'name' => 'Fliesenverlegung',
            'name_i18n' => ['en' => 'Tile laying', 'es' => 'Colocación de baldosas', 'fr' => 'Pose de carrelage', 'it' => 'Posa di piastrelle'],
            'domain' => 'fliesen-bodenleger',
            'risk_level' => 'normal',
            'description' => 'Verlegung mit Untergrundprüfung, Abdichtung, Verlegung und Verfugung.',
            'steps' => [
                ['code' => 'untergrund', 'step_type' => 'choice', 'label' => 'Untergrund prüfen (Feuchte, Ebenheit)'],
                ['code' => 'abdichtung', 'step_type' => 'confirm', 'label' => 'Abdichtung in Nassbereichen ausführen'],
                ['code' => 'verlegen', 'step_type' => 'confirm', 'label' => 'Fliesen verlegen'],
                ['code' => 'verfugen', 'step_type' => 'confirm', 'label' => 'Verfugen und Silikonfugen ziehen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Ergebnis dokumentieren', 'requires_proof_type' => 'photo'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'fliesen/knie', 'category' => 'Physische Belastung', 'hazard' => 'Kniende Tätigkeit', 'measure' => 'Knieschoner, Pausen', 'severity' => 3, 'likelihood' => 4],
        ['code' => 'fliesen/staub', 'category' => 'Gefahrstoffe', 'hazard' => 'Quarzstaub beim Schneiden', 'measure' => 'Nassschnitt, Absaugung, FFP2', 'severity' => 3, 'likelihood' => 3],
        ['code' => 'fliesen/epoxid', 'category' => 'Gefahrstoffe', 'hazard' => 'Hautkontakt mit Epoxidharz', 'measure' => 'Schutzhandschuhe, Hautschutzplan', 'severity' => 3, 'likelihood' => 3],
    ],
    'tags_seed' => [
        '#bad',
        '#boden',
        '#fliese',
        '#estrich',
        '#abdichtung',
    ],
];
