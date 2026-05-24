<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : handwerk.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'handwerk',
    'label' => 'Handwerk / Service allgemein',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'service', 'label' => 'Service'],
            ['code' => 'maintenance', 'label' => 'Wartung'],
            ['code' => 'repair', 'label' => 'Reparatur'],
            ['code' => 'installation', 'label' => 'Installation'],
            ['code' => 'inspection', 'label' => 'Inspektion'],
            ['code' => 'advice', 'label' => 'Beratung'],
            ['code' => 'aufmass', 'label' => 'Aufmaß'],
        ],
        'activity' => [
            ['code' => 'install', 'label' => 'Montieren'],
            ['code' => 'dismantle', 'label' => 'Demontieren'],
            ['code' => 'repair', 'label' => 'Reparieren'],
            ['code' => 'measure', 'label' => 'Messen'],
            ['code' => 'document', 'label' => 'Dokumentieren'],
            ['code' => 'cleanUp', 'label' => 'Baustelle reinigen'],
        ],
        'defect_type' => [
            ['code' => 'mechanical', 'label' => 'Mechanisch'],
            ['code' => 'electrical', 'label' => 'Elektrisch'],
            ['code' => 'plumbing', 'label' => 'Sanitär'],
            ['code' => 'surface', 'label' => 'Oberfläche'],
            ['code' => 'wear', 'label' => 'Verschleiß'],
            ['code' => 'accidental', 'label' => 'Unfallschaden'],
        ],
        'root_cause' => [
            ['code' => 'wear', 'label' => 'Verschleiß'],
            ['code' => 'misuse', 'label' => 'Fehlbedienung'],
            ['code' => 'defect', 'label' => 'Defekt'],
            ['code' => 'installation', 'label' => 'Installationsfehler'],
            ['code' => 'externalDamage', 'label' => 'Externer Schaden'],
        ],
        'result' => [
            ['code' => 'resolved', 'label' => 'Gelöst'],
            ['code' => 'partialResolved', 'label' => 'Teilweise gelöst'],
            ['code' => 'materialMissing', 'label' => 'Material fehlt'],
            ['code' => 'customerDecided', 'label' => 'Kundenentscheidung'],
            ['code' => 'escalated', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'heater', 'label' => 'Heizung'],
            ['code' => 'boiler', 'label' => 'Boiler'],
            ['code' => 'sanitary', 'label' => 'Sanitär'],
            ['code' => 'lighting', 'label' => 'Beleuchtung'],
            ['code' => 'switchgear', 'label' => 'Schaltanlage'],
            ['code' => 'surface', 'label' => 'Oberfläche'],
            ['code' => 'door', 'label' => 'Tür'],
            ['code' => 'window', 'label' => 'Fenster'],
        ],
        'dienstmittel_type' => [
            ['code' => 'tool', 'label' => 'Werkzeug'],
            ['code' => 'ladder', 'label' => 'Leiter'],
            ['code' => 'vehicle', 'label' => 'Fahrzeug'],
            ['code' => 'lift', 'label' => 'Hebebühne'],
            ['code' => 'instrument', 'label' => 'Messgerät'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'repair',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'repair',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'repair',
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
            'entry_type_code' => 'maintenance',
            'required_domain' => 'product_group',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'maintenance',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
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
            'entry_type_code' => 'service',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
            'min_count' => 1,
        ],
    ],
    'procedure_templates' => [
        ['code' => 'HW_SERVICE_CALL'],
        ['code' => 'HW_MAINTENANCE'],
        ['code' => 'HW_REPAIR'],
        ['code' => 'HW_INSTALL_DEVICE'],
        ['code' => 'HW_INSPECTION'],
        ['code' => 'HW_AUFMASS'],
        ['code' => 'HW_HANDOVER_CUSTOMER'],
    ],
    'protocol_templates' => [
        ['code' => 'HW_SERVICEBERICHT'],
        ['code' => 'HW_WARTUNGSPROTOKOLL'],
        ['code' => 'HW_ABNAHMEPROTOKOLL'],
        ['code' => 'HW_INSPEKTION'],
        ['code' => 'HW_AUFMASS'],
        ['code' => 'HW_GERAETEUEBERGABE'],
    ],
    'asset_categories' => [
        'heater',
        'boiler',
        'sanitary',
        'lighting',
        'switchgear',
        'surface',
        'door',
        'window',
        'tool',
        'ladder',
        'vehicle',
        'lift',
    ],
    'tags_seed' => [
        '#wartung',
        '#stoerung',
        '#kundendienst',
        '#baustelle',
        '#notdienst',
        '#ersatzteil',
        '#material',
        '#abnahme',
    ],
];
