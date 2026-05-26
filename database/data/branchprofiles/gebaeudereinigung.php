<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : gebaeudereinigung.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'gebaeudereinigung',
    'label' => 'Gebäudereinigung',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'unterhaltsreinigung', 'label' => 'Unterhaltsreinigung'],
            ['code' => 'grundreinigung', 'label' => 'Grundreinigung'],
            ['code' => 'glasreinigung', 'label' => 'Glasreinigung'],
            ['code' => 'sonderreinigung', 'label' => 'Sonderreinigung'],
            ['code' => 'qualitaetskontrolle', 'label' => 'Qualitätskontrolle'],
            ['code' => 'reklamation', 'label' => 'Reklamation'],
            ['code' => 'begehung', 'label' => 'Begehung'],
        ],
        'activity' => [
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'desinfizieren', 'label' => 'Desinfizieren'],
            ['code' => 'saugen', 'label' => 'Saugen'],
            ['code' => 'wischen', 'label' => 'Wischen'],
            ['code' => 'polieren', 'label' => 'Polieren'],
            ['code' => 'auffuellen', 'label' => 'Auffüllen'],
            ['code' => 'entsorgen', 'label' => 'Entsorgen'],
            ['code' => 'kontrollieren', 'label' => 'Kontrollieren'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
        ],
        'defect_type' => [
            ['code' => 'nichtGereinigt', 'label' => 'Nicht gereinigt'],
            ['code' => 'materialFehlt', 'label' => 'Material fehlt'],
            ['code' => 'zugangFehlt', 'label' => 'Zugang fehlt'],
            ['code' => 'qualitaetsmangel', 'label' => 'Qualitätsmangel'],
            ['code' => 'schaden', 'label' => 'Schaden'],
            ['code' => 'hygienemangel', 'label' => 'Hygienemangel'],
            ['code' => 'kundenbeschwerde', 'label' => 'Kundenbeschwerde'],
        ],
        'root_cause' => [
            ['code' => 'personalEngpass', 'label' => 'Personalengpass'],
            ['code' => 'zugang', 'label' => 'Zugang'],
            ['code' => 'material', 'label' => 'Material'],
            ['code' => 'fremdverschmutzung', 'label' => 'Fremdverschmutzung'],
            ['code' => 'planungsfehler', 'label' => 'Planungsfehler'],
            ['code' => 'nacharbeitNoetig', 'label' => 'Nacharbeit nötig'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'nacharbeit', 'label' => 'Nacharbeit'],
            ['code' => 'nichtMoeglich', 'label' => 'Nicht möglich'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'buero', 'label' => 'Büro'],
            ['code' => 'sanitaer', 'label' => 'Sanitär'],
            ['code' => 'treppenhaus', 'label' => 'Treppenhaus'],
            ['code' => 'glas', 'label' => 'Glas'],
            ['code' => 'boden', 'label' => 'Boden'],
            ['code' => 'kueche', 'label' => 'Küche'],
            ['code' => 'industrie', 'label' => 'Industrie'],
            ['code' => 'medizinisch', 'label' => 'Medizinisch'],
            ['code' => 'aussenbereich', 'label' => 'Außenbereich'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'unterhaltsreinigung',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'unterhaltsreinigung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'qualitaetskontrolle',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'qualitaetskontrolle',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'reklamation',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'reklamation',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'sonderreinigung',
            'required_domain' => 'activity',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'sonderreinigung',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        ['code' => 'GR_UNTERHALT'],
        ['code' => 'GR_GRUNDREINIGUNG'],
        ['code' => 'GR_GLAS'],
        ['code' => 'GR_SONDERREINIGUNG'],
        ['code' => 'GR_QS_KONTROLLE'],
        ['code' => 'GR_REKLAMATION'],
    ],
    'protocol_templates' => [
        ['code' => 'GR_REINIGUNGSNACHWEIS'],
        ['code' => 'GR_OBJEKTPLAN'],
        ['code' => 'GR_QS_PROTOKOLL'],
        ['code' => 'GR_REKLAMATIONSBERICHT'],
        ['code' => 'GR_MATERIALVERBRAUCH'],
        ['code' => 'GR_ABNAHME'],
    ],
    'asset_categories' => [
        'reinigungswagen',
        'einscheibenmaschine',
        'nasssauger',
        'glasreinigungsset',
        'leiter',
        'dosieranlage',
        'psaReinigung',
    ],
    'tags_seed' => [
        '#unterhalt',
        '#sonderreinigung',
        '#glas',
        '#sanitaer',
        '#nacharbeit',
        '#reklamation',
        '#hygiene',
    ],
];
