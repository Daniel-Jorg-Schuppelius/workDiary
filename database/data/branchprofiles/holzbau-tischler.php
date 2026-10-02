<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : holzbau-tischler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'holzbau-tischler',
    'label' => 'Holzbau und Tischler',
    'description' => 'Holzbau, Tischlerei und Schreinerei: Fenster, Türen, Treppen, Möbel und Innenausbau, Holzrahmenbau und Montage mit Aufmaß und Werkstattfertigung.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Fensterfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Bodenfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Stückzahl', 'formula' => '00', 'unit' => 'Stk', 'factor' => '1'],
            ['label' => 'Laufende Meter', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
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
            ['code' => 'fenster', 'label' => 'Fenster'],
            ['code' => 'tueren', 'label' => 'Türen'],
            ['code' => 'treppe', 'label' => 'Treppe'],
            ['code' => 'moebel', 'label' => 'Möbel/Innenausbau'],
            ['code' => 'holzbau', 'label' => 'Holzrahmenbau'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'aufmessen', 'label' => 'Aufmessen'],
            ['code' => 'fertigen', 'label' => 'In der Werkstatt fertigen'],
            ['code' => 'montieren', 'label' => 'Montieren'],
            ['code' => 'einstellen', 'label' => 'Einstellen'],
            ['code' => 'abdichten', 'label' => 'Abdichten'],
            ['code' => 'reinigen', 'label' => 'Baustelle reinigen'],
        ],
        'defect_type' => [
            ['code' => 'verzug', 'label' => 'Verzug'],
            ['code' => 'klemmt', 'label' => 'Klemmt/schließt nicht'],
            ['code' => 'oberflaeche', 'label' => 'Oberflächenschaden'],
            ['code' => 'undicht', 'label' => 'Undicht'],
            ['code' => 'beschlag', 'label' => 'Beschlag defekt'],
        ],
        'root_cause' => [
            ['code' => 'feuchte', 'label' => 'Feuchte'],
            ['code' => 'montage', 'label' => 'Montage'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'nutzung', 'label' => 'Nutzung'],
        ],
        'result' => [
            ['code' => 'fertig', 'label' => 'Fertig'],
            ['code' => 'nachstellen', 'label' => 'Nachstellen nötig'],
            ['code' => 'ersatzteil', 'label' => 'Ersatzteil bestellt'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'fenster', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'HT_MONTAGE',
            'name' => 'Montage Fenster/Türen',
            'name_i18n' => ['en' => 'Window/door installation', 'es' => 'Montaje de ventanas/puertas', 'fr' => 'Pose de fenêtres/portes', 'it' => 'Montaggio finestre/porte'],
            'domain' => 'holzbau-tischler',
            'risk_level' => 'normal',
            'description' => 'Montage mit Prüfung, Einbau, Abdichtung, Funktionsprüfung und Übergabe.',
            'steps' => [
                ['code' => 'pruefen', 'step_type' => 'confirm', 'label' => 'Bauteil und Öffnung prüfen'],
                ['code' => 'einbau', 'step_type' => 'confirm', 'label' => 'Einbauen und befestigen'],
                ['code' => 'abdichten', 'step_type' => 'confirm', 'label' => 'Anschlussfugen abdichten'],
                ['code' => 'funktion', 'step_type' => 'confirm', 'label' => 'Funktion prüfen und einstellen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Einbau dokumentieren', 'requires_proof_type' => 'photo'],
            ],
        ],
    ],
    'protocol_templates' => [
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_AUFMASS'],
    ],
    // Gefährdungskatalog (Feature 132): Schwere und Wahrscheinlichkeit 1–5.
    'hazard_catalog' => [
        ['code' => 'holz/maschinen', 'category' => 'Mechanische Gefährdung', 'hazard' => 'Schnittverletzungen an Holzbearbeitungsmaschinen', 'measure' => 'Schutzhauben, Schiebestöcke, Unterweisung', 'severity' => 4, 'likelihood' => 3],
        ['code' => 'holz/staub', 'category' => 'Gefahrstoffe', 'hazard' => 'Hartholzstaub (krebserzeugend)', 'measure' => 'Absaugung, FFP2-Maske', 'severity' => 3, 'likelihood' => 4],
        ['code' => 'holz/laerm', 'category' => 'Lärm', 'hazard' => 'Lärm an Maschinen', 'measure' => 'Gehörschutz', 'severity' => 2, 'likelihood' => 4],
    ],
    'tags_seed' => [
        '#fenster',
        '#tueren',
        '#treppe',
        '#moebel',
        '#montage',
    ],
];
