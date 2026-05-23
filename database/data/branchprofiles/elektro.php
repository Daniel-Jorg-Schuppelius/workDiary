<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : elektro.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Classification\ClassificationRequirementSeverity;

return [
    'code' => 'elektro',
    'label' => 'Elektro',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'stoerung', 'label' => 'Stoerung'],
            ['code' => 'pruefung', 'label' => 'Pruefung'],
            ['code' => 'messung', 'label' => 'Messung'],
            ['code' => 'verteilerarbeit', 'label' => 'Verteilerarbeit'],
            ['code' => 'eCheck', 'label' => 'E-Check'],
            ['code' => 'wallbox', 'label' => 'Wallbox'],
            ['code' => 'pvAnschluss', 'label' => 'PV-Anschluss'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
        ],
        'activity' => [
            ['code' => 'freischalten', 'label' => 'Freischalten'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'anschliessen', 'label' => 'Anschliessen'],
            ['code' => 'verdrahten', 'label' => 'Verdrahten'],
            ['code' => 'beschriften', 'label' => 'Beschriften'],
            ['code' => 'pruefen', 'label' => 'Pruefen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'fehlersuche', 'label' => 'Fehlersuche'],
            ['code' => 'inbetriebnehmen', 'label' => 'Inbetriebnehmen'],
        ],
        'defect_type' => [
            ['code' => 'kurzschluss', 'label' => 'Kurzschluss'],
            ['code' => 'erdschluss', 'label' => 'Erdschluss'],
            ['code' => 'ueberlast', 'label' => 'Ueberlast'],
            ['code' => 'defekteSicherung', 'label' => 'Defekte Sicherung'],
            ['code' => 'loseKlemme', 'label' => 'Lose Klemme'],
            ['code' => 'isolationsfehler', 'label' => 'Isolationsfehler'],
            ['code' => 'falscheBeschriftung', 'label' => 'Falsche Beschriftung'],
            ['code' => 'messwertAbweichung', 'label' => 'Messwertabweichung'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiss'],
            ['code' => 'feuchtigkeit', 'label' => 'Feuchtigkeit'],
            ['code' => 'installation', 'label' => 'Installationsfehler'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'fremdgewerk', 'label' => 'Fremdgewerk'],
            ['code' => 'materialfehler', 'label' => 'Materialfehler'],
            ['code' => 'planungsfehler', 'label' => 'Planungsfehler'],
        ],
        'result' => [
            ['code' => 'behoben', 'label' => 'Behoben'],
            ['code' => 'teilBehoben', 'label' => 'Teilweise behoben'],
            ['code' => 'freigegeben', 'label' => 'Freigegeben'],
            ['code' => 'nichtFreigegeben', 'label' => 'Nicht freigegeben'],
            ['code' => 'nacharbeitNoetig', 'label' => 'Nacharbeit noetig'],
            ['code' => 'materialFehlt', 'label' => 'Material fehlt'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'verteiler', 'label' => 'Verteiler'],
            ['code' => 'sicherung', 'label' => 'Sicherung'],
            ['code' => 'leitung', 'label' => 'Leitung'],
            ['code' => 'steckdose', 'label' => 'Steckdose'],
            ['code' => 'beleuchtung', 'label' => 'Beleuchtung'],
            ['code' => 'wallbox', 'label' => 'Wallbox'],
            ['code' => 'pvWechselrichter', 'label' => 'PV-Wechselrichter'],
            ['code' => 'zaehlerplatz', 'label' => 'Zaehlerplatz'],
            ['code' => 'netzwerk', 'label' => 'Netzwerk'],
            ['code' => 'smartHome', 'label' => 'Smart Home'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'stoerung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'installation',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'installation',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'eCheck',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'wallbox',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'pvAnschluss',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        ['code' => 'EL_STOERUNG'],
        ['code' => 'EL_INSTALLATION'],
        ['code' => 'EL_VERTEILER'],
        ['code' => 'EL_ECHECK'],
        ['code' => 'EL_WALLBOX'],
        ['code' => 'EL_INBETRIEBNAHME'],
    ],
    'protocol_templates' => [
        ['code' => 'EL_PRUEFPROTOKOLL'],
        ['code' => 'EL_MESSPROTOKOLL'],
        ['code' => 'EL_SERVICEBERICHT'],
        ['code' => 'EL_ABNAHME'],
        ['code' => 'EL_VERTEILER_DOKU'],
        ['code' => 'EL_WALLBOX_INBETRIEBNAHME'],
    ],
    'asset_categories' => [
        'multimeter',
        'installationstester',
        'leiter',
        'servicefahrzeug',
        'crimpzange',
        'waermebildkamera',
        'labeldrucker',
        'bohrmaschine',
        'messadapter',
        'psaElektro',
    ],
    'tags_seed' => [
        '#vde',
        '#freischaltung',
        '#messwerte',
        '#verteiler',
        '#wallbox',
        '#stoerung',
        '#nacharbeit',
        '#kundenabnahme',
    ],
];
