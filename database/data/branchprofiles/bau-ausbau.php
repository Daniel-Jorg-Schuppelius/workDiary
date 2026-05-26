<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : bau-ausbau.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'bau-ausbau',
    'label' => 'Bau, Ausbau und Trockenbau',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'bautagesbericht', 'label' => 'Bautagesbericht'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
            ['code' => 'montage', 'label' => 'Montage'],
            ['code' => 'mangel', 'label' => 'Mangel'],
            ['code' => 'nachtrag', 'label' => 'Nachtrag'],
            ['code' => 'teilabnahme', 'label' => 'Teilabnahme'],
            ['code' => 'material', 'label' => 'Materialbezug'],
            ['code' => 'behinderung', 'label' => 'Behinderungsanzeige'],
            ['code' => 'restarbeit', 'label' => 'Restarbeit'],
        ],
        'activity' => [
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'montieren', 'label' => 'Montieren'],
            ['code' => 'spachteln', 'label' => 'Spachteln'],
            ['code' => 'schleifen', 'label' => 'Schleifen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'pruefen', 'label' => 'Prüfen'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'koordinieren', 'label' => 'Koordinieren'],
            ['code' => 'nacharbeiten', 'label' => 'Nacharbeiten'],
        ],
        'defect_type' => [
            ['code' => 'massabweichung', 'label' => 'Maßabweichung'],
            ['code' => 'materialfehler', 'label' => 'Materialfehler'],
            ['code' => 'bauseitigerMangel', 'label' => 'Bauseitiger Mangel'],
            ['code' => 'feuchtigkeit', 'label' => 'Feuchtigkeit'],
            ['code' => 'beschaedigung', 'label' => 'Beschädigung'],
            ['code' => 'planabweichung', 'label' => 'Planabweichung'],
        ],
        'root_cause' => [
            ['code' => 'vorleistungFehlt', 'label' => 'Vorleistung fehlt'],
            ['code' => 'wetter', 'label' => 'Wetter'],
            ['code' => 'planung', 'label' => 'Planung'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'fremdgewerk', 'label' => 'Fremdgewerk'],
            ['code' => 'bauherrAenderung', 'label' => 'Bauherr-Änderung'],
            ['code' => 'lieferverzug', 'label' => 'Lieferverzug'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'offen', 'label' => 'Offen'],
            ['code' => 'nachtragNoetig', 'label' => 'Nachtrag nötig'],
            ['code' => 'behindert', 'label' => 'Behindert'],
            ['code' => 'abgenommen', 'label' => 'Abgenommen'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'wand', 'label' => 'Wand'],
            ['code' => 'decke', 'label' => 'Decke'],
            ['code' => 'boden', 'label' => 'Boden'],
            ['code' => 'tuer', 'label' => 'Tür'],
            ['code' => 'fenster', 'label' => 'Fenster'],
            ['code' => 'trockenbau', 'label' => 'Trockenbau'],
            ['code' => 'daemmung', 'label' => 'Dämmung'],
            ['code' => 'malerarbeiten', 'label' => 'Malerarbeiten'],
            ['code' => 'fliesen', 'label' => 'Fliesen'],
            ['code' => 'fassade', 'label' => 'Fassade'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'bautagesbericht',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'aufmass',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'aufmass',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'mangel',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'mangel',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'nachtrag',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'teilabnahme',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        ['code' => 'BAU_TAGESBERICHT'],
        ['code' => 'BAU_AUFMASS'],
        ['code' => 'BAU_MANGEL'],
        ['code' => 'BAU_NACHTRAG'],
        ['code' => 'BAU_TEILABNAHME'],
        ['code' => 'BAU_RESTARBEIT'],
    ],
    'protocol_templates' => [
        ['code' => 'BAU_TAGESBERICHT'],
        ['code' => 'BAU_AUFMASSPROTOKOLL'],
        ['code' => 'BAU_MAENGELLISTE'],
        ['code' => 'BAU_NACHTRAGSPROTOKOLL'],
        ['code' => 'BAU_ABNAHME'],
        ['code' => 'BAU_MATERIALVERBRAUCH'],
    ],
    'asset_categories' => [
        'transporter',
        'laserentfernungsmesser',
        'nivellierlaser',
        'akkuschrauber',
        'geruest',
        'baustrahler',
        'staubsauger',
        'leiter',
    ],
    'tags_seed' => [
        '#baustelle',
        '#aufmass',
        '#nachtrag',
        '#mangel',
        '#restpunkte',
        '#wetter',
        '#fremdgewerk',
        '#abnahme',
    ],
];
