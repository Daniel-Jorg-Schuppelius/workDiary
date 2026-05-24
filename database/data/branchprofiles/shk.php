<?php

/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : shk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'shk',
    'label' => 'SHK',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'wartung', 'label' => 'Wartung'],
            ['code' => 'stoerung', 'label' => 'Stoerung'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'inbetriebnahme', 'label' => 'Inbetriebnahme'],
            ['code' => 'druckpruefung', 'label' => 'Druckpruefung'],
            ['code' => 'dichtheitspruefung', 'label' => 'Dichtheitspruefung'],
            ['code' => 'abnahme', 'label' => 'Abnahme'],
            ['code' => 'notdienst', 'label' => 'Notdienst'],
        ],
        'activity' => [
            ['code' => 'entlueften', 'label' => 'Entlueften'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'tauschen', 'label' => 'Tauschen'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'abdichten', 'label' => 'Abdichten'],
            ['code' => 'einstellen', 'label' => 'Einstellen'],
            ['code' => 'spuelen', 'label' => 'Spuelen'],
            ['code' => 'pruefen', 'label' => 'Pruefen'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'leckage', 'label' => 'Leckage'],
            ['code' => 'druckverlust', 'label' => 'Druckverlust'],
            ['code' => 'heizungsstoerung', 'label' => 'Heizungsstoerung'],
            ['code' => 'verstopfung', 'label' => 'Verstopfung'],
            ['code' => 'korrosion', 'label' => 'Korrosion'],
            ['code' => 'sensorDefekt', 'label' => 'Sensordefekt'],
            ['code' => 'brennerStoerung', 'label' => 'Brennerstoerung'],
            ['code' => 'geraeusch', 'label' => 'Geraeusch'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiss'],
            ['code' => 'verkalkung', 'label' => 'Verkalkung'],
            ['code' => 'frost', 'label' => 'Frost'],
            ['code' => 'montagefehler', 'label' => 'Montagefehler'],
            ['code' => 'nutzung', 'label' => 'Nutzung'],
            ['code' => 'materialfehler', 'label' => 'Materialfehler'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
        ],
        'result' => [
            ['code' => 'behoben', 'label' => 'Behoben'],
            ['code' => 'teilBehoben', 'label' => 'Teilweise behoben'],
            ['code' => 'dicht', 'label' => 'Dicht'],
            ['code' => 'nichtDicht', 'label' => 'Nicht dicht'],
            ['code' => 'ersatzteilNoetig', 'label' => 'Ersatzteil noetig'],
            ['code' => 'kundenentscheidung', 'label' => 'Kundenentscheidung'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'heizung', 'label' => 'Heizung'],
            ['code' => 'therme', 'label' => 'Therme'],
            ['code' => 'boiler', 'label' => 'Boiler'],
            ['code' => 'pumpe', 'label' => 'Pumpe'],
            ['code' => 'ventil', 'label' => 'Ventil'],
            ['code' => 'rohrleitung', 'label' => 'Rohrleitung'],
            ['code' => 'heizkoerper', 'label' => 'Heizkoerper'],
            ['code' => 'sanitaerObjekt', 'label' => 'Sanitaerobjekt'],
            ['code' => 'klimaGeraet', 'label' => 'Klimageraet'],
            ['code' => 'lueftung', 'label' => 'Lueftung'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'wartung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'wartung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'druckpruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'dichtheitspruefung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
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
            'entry_type_code' => 'inbetriebnahme',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'inbetriebnahme',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        ['code' => 'SHK_WARTUNG'],
        ['code' => 'SHK_STOERUNG'],
        ['code' => 'SHK_DRUCKPRUEFUNG'],
        ['code' => 'SHK_DICHTHEIT'],
        ['code' => 'SHK_INBETRIEBNAHME'],
        ['code' => 'SHK_NOTDIENST'],
    ],
    'protocol_templates' => [
        ['code' => 'SHK_WARTUNGSPROTOKOLL'],
        ['code' => 'SHK_DRUCKPROTOKOLL'],
        ['code' => 'SHK_DICHTHEITSPROTOKOLL'],
        ['code' => 'SHK_ABNAHME'],
        ['code' => 'SHK_ANLAGENAKTE'],
        ['code' => 'SHK_SERVICEBERICHT'],
    ],
    'asset_categories' => [
        'servicefahrzeug',
        'pressmaschine',
        'rohrkamera',
        'lecksuchgeraet',
        'druckpruefpumpe',
        'messgeraetAbgas',
        'leiter',
        'werkzeugkoffer',
    ],
    'tags_seed' => [
        '#notdienst',
        '#leckage',
        '#wartung',
        '#druckpruefung',
        '#ersatzteil',
        '#kundenabnahme',
        '#anlage',
    ],
];
