<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : maler.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

// Gewerke-Profil (MVP-1062).
return [
    'code' => 'maler',
    'label' => 'Maler und Lackierer',
    'description' => 'Maler und Lackierer: Innen- und Fassadenanstrich, Tapezieren, Lackieren, Spachtel- und Wärmedämmarbeiten mit Aufmaß nach Wandfläche und Abnahme.',
    'version' => 1,
    // MVP-1053/1058: § 35a bei Privatkunden ausweisen, Aufmaß-Formelvorlagen des Gewerks; eigene Einstellungen bleiben.
    'settings' => [
        'invoicing.labour_cost_disclosure' => 'private_customers',
        'takeoff.presets' => [
            ['label' => 'Wandfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Deckenfläche', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Öffnung abziehen', 'formula' => '04', 'unit' => 'm2', 'factor' => '-1'],
            ['label' => 'Laibung', 'formula' => '04', 'unit' => 'm2', 'factor' => '1'],
            ['label' => 'Sockelleiste', 'formula' => '00', 'unit' => 'm', 'factor' => '1'],
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
            ['code' => 'innenanstrich', 'label' => 'Innenanstrich'],
            ['code' => 'fassade', 'label' => 'Fassadenanstrich'],
            ['code' => 'tapezieren', 'label' => 'Tapezieren'],
            ['code' => 'lackieren', 'label' => 'Lackieren'],
            ['code' => 'wdvs', 'label' => 'Wärmedämmung (WDVS)'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
        ],
        'activity' => [
            ['code' => 'abkleben', 'label' => 'Abkleben'],
            ['code' => 'spachteln', 'label' => 'Spachteln'],
            ['code' => 'grundieren', 'label' => 'Grundieren'],
            ['code' => 'streichen', 'label' => 'Streichen'],
            ['code' => 'tapezieren', 'label' => 'Tapezieren'],
            ['code' => 'reinigen', 'label' => 'Baustelle reinigen'],
        ],
        'defect_type' => [
            ['code' => 'risse', 'label' => 'Risse'],
            ['code' => 'abplatzungen', 'label' => 'Abplatzungen'],
            ['code' => 'feuchte', 'label' => 'Feuchteschaden'],
            ['code' => 'schimmel', 'label' => 'Schimmel'],
            ['code' => 'flecken', 'label' => 'Flecken/Verfärbung'],
        ],
        'root_cause' => [
            ['code' => 'untergrund', 'label' => 'Untergrund'],
            ['code' => 'feuchtigkeit', 'label' => 'Feuchtigkeit'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'verarbeitung', 'label' => 'Verarbeitung'],
        ],
        'result' => [
            ['code' => 'fertig', 'label' => 'Fertig'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'trocknung', 'label' => 'Trocknung abwarten'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
        ],
    ],
    'classification_requirements' => [
        ['entry_type_code' => 'innenanstrich', 'required_domain' => 'result', 'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value, 'severity' => ClassificationRequirementSeverity::Soft->value, 'min_count' => 1],
    ],
    'procedure_templates' => [
        [
            'code' => 'MA_ANSTRICH',
            'name' => 'Anstricharbeiten',
            'name_i18n' => ['en' => 'Painting work', 'es' => 'Trabajos de pintura', 'fr' => 'Travaux de peinture', 'it' => 'Lavori di tinteggiatura'],
            'domain' => 'maler',
            'risk_level' => 'normal',
            'description' => 'Anstrich mit Untergrundprüfung, Vorbereitung, Beschichtung und Abnahme.',
            'steps' => [
                ['code' => 'untergrund', 'step_type' => 'choice', 'label' => 'Untergrund prüfen und einstufen'],
                ['code' => 'vorbereiten', 'step_type' => 'confirm', 'label' => 'Abdecken, abkleben, spachteln'],
                ['code' => 'beschichten', 'step_type' => 'confirm', 'label' => 'Grund- und Schlussbeschichtung ausführen'],
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
        ['code' => 'maler/leiter', 'category' => 'Absturz', 'hazard' => 'Absturz von Leitern und Gerüsten', 'measure' => 'Gerüst statt Leiter, Leitern prüfen', 'severity' => 4, 'likelihood' => 3],
        ['code' => 'maler/loesemittel', 'category' => 'Gefahrstoffe', 'hazard' => 'Lösemitteldämpfe beim Lackieren', 'measure' => 'Lösemittelarme Produkte, lüften, Atemschutz', 'severity' => 3, 'likelihood' => 3],
        ['code' => 'maler/schleifstaub', 'category' => 'Gefahrstoffe', 'hazard' => 'Schleifstaub', 'measure' => 'Absaugung, FFP2-Maske', 'severity' => 2, 'likelihood' => 4],
    ],
    'tags_seed' => [
        '#innen',
        '#fassade',
        '#tapete',
        '#lack',
        '#abnahme',
    ],
];
