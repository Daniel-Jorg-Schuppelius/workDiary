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
        'module.bau',
        'module.fuhrpark',
        'module.lager',
        'module.standorterfassung',
    ],
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
        // Gewerke / Nachunternehmer-Kategorien (Auswahl je Eintrag; konkreter
        // Betrieb liegt im Lieferanten-/Nachunternehmer-Stamm).
        'trade' => [
            ['code' => 'rohbau', 'label' => 'Rohbau'],
            ['code' => 'trockenbau', 'label' => 'Trockenbau'],
            ['code' => 'elektro', 'label' => 'Elektro'],
            ['code' => 'sanitaer', 'label' => 'Sanitär / Heizung'],
            ['code' => 'maler', 'label' => 'Maler / Lackierer'],
            ['code' => 'bodenleger', 'label' => 'Bodenleger'],
            ['code' => 'fenster_tueren', 'label' => 'Fenster / Türen'],
            ['code' => 'dachdecker', 'label' => 'Dachdecker'],
            ['code' => 'geruestbau', 'label' => 'Gerüstbau'],
        ],
        // Genehmigungsarten (Auswahl/Filter je Eintrag). Status, Frist und
        // Nachweis werden im Genehmigungs-Register (Permit) geführt.
        'permit_type' => [
            ['code' => 'baugenehmigung', 'label' => 'Baugenehmigung'],
            ['code' => 'abnahme_bauamt', 'label' => 'Bauamtliche Abnahme'],
            ['code' => 'statik_freigabe', 'label' => 'Statik-Freigabe'],
            ['code' => 'brandschutznachweis', 'label' => 'Brandschutznachweis'],
            ['code' => 'geruestabnahme', 'label' => 'Gerüstabnahme'],
            ['code' => 'entsorgungsnachweis', 'label' => 'Entsorgungsnachweis'],
            ['code' => 'aufgrabung', 'label' => 'Aufgrabungsgenehmigung'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'montage',
            'required_domain' => 'trade',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'allow_multi' => true,
            'min_count' => 1,
        ],
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
        [
            'code' => 'BAU_TAGESBERICHT',
            'name' => 'Bautagesbericht',
            'domain' => 'bau-ausbau',
            'risk_level' => 'normal',
            'description' => 'Täglicher Bautagesbericht mit Wetter, Personal, Leistung und Behinderungen.',
            'steps' => [
                ['code' => 'wetter', 'step_type' => 'choice', 'label' => 'Wetterlage erfassen'],
                ['code' => 'personal', 'step_type' => 'number', 'label' => 'Anzahl eingesetzter Arbeitskräfte'],
                ['code' => 'leistung', 'step_type' => 'text', 'label' => 'Erbrachte Leistung beschreiben'],
                ['code' => 'behinderung', 'step_type' => 'confirm', 'label' => 'Behinderung/Bedenken vermerken (falls vorhanden)', 'required' => false, 'blocking' => false],
                ['code' => 'fotos', 'step_type' => 'photo', 'label' => 'Baufortschritt fotografieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
            ],
        ],
        [
            'code' => 'BAU_AUFMASS',
            'name' => 'Aufmaß',
            'domain' => 'bau-ausbau',
            'risk_level' => 'normal',
            'description' => 'Aufmaß der erbrachten Mengen mit Nachweis.',
            'steps' => [
                ['code' => 'bereich', 'step_type' => 'choice', 'label' => 'Bauteil/Bereich wählen'],
                ['code' => 'masse', 'step_type' => 'messreihe', 'label' => 'Maße/Mengen erfassen'],
                ['code' => 'foto', 'step_type' => 'photo', 'label' => 'Aufmaß dokumentieren', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'photo'],
                ['code' => 'bestaetigung', 'step_type' => 'signature', 'label' => 'Aufmaß bestätigen lassen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'BAU_TEILABNAHME',
            'name' => 'Teilabnahme',
            'domain' => 'bau-ausbau',
            'risk_level' => 'normal',
            'description' => 'Förmliche Teilabnahme mit Mängelaufnahme und Abnahmeprotokoll.',
            'steps' => [
                ['code' => 'leistungPruefen', 'step_type' => 'confirm', 'label' => 'Leistung gegen Leistungsverzeichnis prüfen'],
                ['code' => 'maengel', 'step_type' => 'confirm', 'label' => 'Mängel/Restpunkte aufnehmen (falls vorhanden)', 'required' => false, 'blocking' => false],
                ['code' => 'protokoll', 'step_type' => 'signature', 'label' => 'Abnahmeprotokoll unterschreiben lassen', 'requires_proof_type' => 'signature'],
            ],
        ],
        ['code' => 'BAU_MANGEL'],
        ['code' => 'BAU_NACHTRAG'],
        ['code' => 'BAU_RESTARBEIT'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
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
