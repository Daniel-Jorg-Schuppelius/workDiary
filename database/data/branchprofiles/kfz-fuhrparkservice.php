<?php
/*
 * Created on   : Sun Jul 13 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : kfz-fuhrparkservice.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'kfz-fuhrparkservice',
    'label' => 'Kfz- und Fuhrparkservice',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'annahme', 'label' => 'Fahrzeugannahme'],
            ['code' => 'wartung', 'label' => 'Wartung/Service'],
            ['code' => 'reparatur', 'label' => 'Reparatur'],
            ['code' => 'diagnose', 'label' => 'Diagnose'],
            ['code' => 'reifenwechsel', 'label' => 'Reifenwechsel'],
            ['code' => 'schaden', 'label' => 'Schaden'],
            ['code' => 'huAu', 'label' => 'HU/AU-Vorbereitung'],
            ['code' => 'uebergabe', 'label' => 'Fahrzeugübergabe'],
            ['code' => 'rueckgabe', 'label' => 'Fahrzeugrückgabe'],
            ['code' => 'nachkalkulation', 'label' => 'Nachkalkulation'],
        ],
        'activity' => [
            ['code' => 'pruefen', 'label' => 'Prüfen'],
            ['code' => 'messen', 'label' => 'Messen'],
            ['code' => 'tauschen', 'label' => 'Tauschen'],
            ['code' => 'reinigen', 'label' => 'Reinigen'],
            ['code' => 'kalibrieren', 'label' => 'Kalibrieren'],
            ['code' => 'probefahrt', 'label' => 'Probefahrt'],
            ['code' => 'dokumentieren', 'label' => 'Dokumentieren'],
            ['code' => 'bestellen', 'label' => 'Bestellen'],
            ['code' => 'uebergeben', 'label' => 'Übergeben'],
        ],
        'defect_type' => [
            ['code' => 'motorschaden', 'label' => 'Motorschaden'],
            ['code' => 'bremsen', 'label' => 'Bremsen'],
            ['code' => 'elektrik', 'label' => 'Elektrik'],
            ['code' => 'karosserie', 'label' => 'Karosserie'],
            ['code' => 'reifen', 'label' => 'Reifen'],
            ['code' => 'scheibe', 'label' => 'Scheibe'],
            ['code' => 'lack', 'label' => 'Lack'],
            ['code' => 'fahrwerk', 'label' => 'Fahrwerk'],
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
        ],
        'root_cause' => [
            ['code' => 'verschleiss', 'label' => 'Verschleiß'],
            ['code' => 'unfall', 'label' => 'Unfall'],
            ['code' => 'bedienfehler', 'label' => 'Bedienfehler'],
            ['code' => 'wartungUeberfaellig', 'label' => 'Wartung überfällig'],
            ['code' => 'material', 'label' => 'Materialfehler'],
            ['code' => 'fremdeinwirkung', 'label' => 'Fremdeinwirkung'],
        ],
        'result' => [
            ['code' => 'erledigt', 'label' => 'Erledigt'],
            ['code' => 'teilErledigt', 'label' => 'Teilweise erledigt'],
            ['code' => 'fahrbereit', 'label' => 'Fahrbereit'],
            ['code' => 'nichtFahrbereit', 'label' => 'Nicht fahrbereit'],
            ['code' => 'teileNoetig', 'label' => 'Teile nötig'],
            ['code' => 'kundeInformiert', 'label' => 'Kunde informiert'],
            ['code' => 'eskaliert', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'pkw', 'label' => 'Pkw'],
            ['code' => 'transporter', 'label' => 'Transporter'],
            ['code' => 'lkw', 'label' => 'Lkw'],
            ['code' => 'anhaenger', 'label' => 'Anhänger'],
            ['code' => 'reifen', 'label' => 'Reifen'],
            ['code' => 'bremse', 'label' => 'Bremse'],
            ['code' => 'motor', 'label' => 'Motor'],
            ['code' => 'batterie', 'label' => 'Batterie'],
            ['code' => 'karosserie', 'label' => 'Karosserie'],
            ['code' => 'innenraum', 'label' => 'Innenraum'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'annahme',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
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
            'entry_type_code' => 'schaden',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'schaden',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'schaden',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'reifenwechsel',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'uebergabe',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'diagnose',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        [
            'code' => 'KFZ_ANNAHME',
            'name' => 'Fahrzeugannahme mit Zustandserfassung',
            'domain' => 'kfz',
            'risk_level' => 'normal',
            'description' => 'Dokumentierte Annahme mit Kilometerstand, Zustand und Kundenauftrag.',
            'steps' => [
                ['code' => 'fahrzeug', 'step_type' => 'text', 'label' => 'Fahrzeug/Kennzeichen und Kilometerstand erfassen'],
                ['code' => 'zustand', 'step_type' => 'photo', 'label' => 'Zustand dokumentieren (Rundgang)', 'requires_proof_type' => 'photo'],
                ['code' => 'auftrag', 'step_type' => 'text', 'label' => 'Kundenauftrag festhalten'],
                ['code' => 'freigabe', 'step_type' => 'confirm', 'label' => 'Annahme bestätigen'],
            ],
        ],
        [
            'code' => 'KFZ_UEBERGABE',
            'name' => 'Fahrzeugübergabe mit Unterschrift',
            'domain' => 'kfz',
            'risk_level' => 'normal',
            'description' => 'Nachvollziehbare Übergabe mit Kilometerstand, Zustand und Quittung.',
            'steps' => [
                ['code' => 'kilometer', 'step_type' => 'text', 'label' => 'Kilometerstand bei Übergabe erfassen'],
                ['code' => 'zustand', 'step_type' => 'confirm', 'label' => 'Zustand mit Kunde durchgehen'],
                ['code' => 'quittung', 'step_type' => 'signature', 'label' => 'Übergabe quittieren', 'requires_proof_type' => 'signature'],
            ],
        ],
        ['code' => 'KFZ_WARTUNG'],
        ['code' => 'KFZ_DIAGNOSE'],
        ['code' => 'KFZ_REPARATUR'],
        ['code' => 'KFZ_REIFEN'],
        ['code' => 'KFZ_SCHADEN'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
    'protocol_templates' => [
        ['code' => 'KFZ_ANNAHMEPROTOKOLL'],
        ['code' => 'KFZ_SERVICEBERICHT'],
        ['code' => 'KFZ_DIAGNOSEBERICHT'],
        ['code' => 'KFZ_SCHADENPROTOKOLL'],
        ['code' => 'KFZ_REIFENEINLAGERUNG'],
        ['code' => 'KFZ_UEBERGABE'],
    ],
    'asset_categories' => [
        'hebebuehne',
        'diagnosegeraet',
        'drehmomentschluessel',
        'reifenmontiermaschine',
        'wuchtmaschine',
        'servicefahrzeug',
        'batterietester',
        'ersatzfahrzeug',
    ],
    'tags_seed' => [
        '#fahrzeugakte',
        '#wartung',
        '#reifen',
        '#schaden',
        '#hu-au',
        '#ersatzteil',
        '#probefahrt',
        '#uebergabe',
    ],
    'maintenance_plans_seed' => [
        ['code' => 'KFZ-HEBEBUEHNE-12M', 'label' => 'Hebebühne UVV-Prüfung jährlich', 'category_code' => 'hebebuehne', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'KFZ-DIAGNOSE-12M', 'label' => 'Diagnosegerät Update/Prüfung', 'category_code' => 'diagnosegeraet', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
        ['code' => 'KFZ-DREHMOMENT-12M', 'label' => 'Drehmomentschlüssel kalibrieren', 'category_code' => 'drehmomentschluessel', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 14],
        ['code' => 'KFZ-ERSATZFZG-12M', 'label' => 'Ersatzfahrzeug HU/Service-Check', 'category_code' => 'ersatzfahrzeug', 'interval_kind' => 'months', 'interval_value' => 12, 'tolerance_days' => 30],
    ],
    'sla_contracts_seed' => [
        [
            'code' => 'SLA-KFZ-STANDARD',
            'label' => 'Fuhrparkservice Standard-SLA',
            'is_default' => true,
            'priority_table' => [
                'low'    => ['reaction_minutes' => 1440, 'resolution_minutes' => 10080],
                'normal' => ['reaction_minutes' => 480,  'resolution_minutes' => 2880],
                'high'   => ['reaction_minutes' => 120,  'resolution_minutes' => 1440],
                'urgent' => ['reaction_minutes' => 60,   'resolution_minutes' => 480],
            ],
            'business_hours' => [
                ['weekday' => 1, 'from' => '07:00', 'to' => '18:00'],
                ['weekday' => 2, 'from' => '07:00', 'to' => '18:00'],
                ['weekday' => 3, 'from' => '07:00', 'to' => '18:00'],
                ['weekday' => 4, 'from' => '07:00', 'to' => '18:00'],
                ['weekday' => 5, 'from' => '07:00', 'to' => '17:00'],
                ['weekday' => 6, 'from' => '08:00', 'to' => '13:00'],
            ],
        ],
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): Halter-/Fahrerdaten,
    // aber keine umfangreiche eigene Verarbeitung → GVV/DSFA standardmäßig aus.
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required', 'active' => false],
        ['key' => 'dpia_required', 'active' => false],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],
];
