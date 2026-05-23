<?php

/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : it.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\ClassificationRequirementPhase;
use App\Enums\Classification\ClassificationRequirementSeverity;

return [
    'code' => 'it',
    'label' => 'IT-Service / Managed Services',
    'version' => 1,
    'classifications' => [
        'entry_type' => [
            ['code' => 'incident', 'label' => 'Incident'],
            ['code' => 'request', 'label' => 'Request'],
            ['code' => 'change', 'label' => 'Change'],
            ['code' => 'problem', 'label' => 'Problem'],
            ['code' => 'maintenance', 'label' => 'Maintenance'],
            ['code' => 'advice', 'label' => 'Beratung'],
        ],
        'activity' => [
            ['code' => 'analysis', 'label' => 'Analyse'],
            ['code' => 'configure', 'label' => 'Konfigurieren'],
            ['code' => 'deploy', 'label' => 'Deploy'],
            ['code' => 'patch', 'label' => 'Patchen'],
            ['code' => 'backup', 'label' => 'Backup'],
            ['code' => 'restore', 'label' => 'Restore'],
            ['code' => 'monitor', 'label' => 'Monitoring'],
        ],
        'defect_type' => [
            ['code' => 'hardware', 'label' => 'Hardware'],
            ['code' => 'software', 'label' => 'Software'],
            ['code' => 'network', 'label' => 'Netzwerk'],
            ['code' => 'security', 'label' => 'Sicherheit'],
            ['code' => 'user', 'label' => 'Benutzer'],
            ['code' => 'integration', 'label' => 'Integration'],
        ],
        'root_cause' => [
            ['code' => 'bug', 'label' => 'Bug'],
            ['code' => 'misconfiguration', 'label' => 'Fehlkonfiguration'],
            ['code' => 'capacity', 'label' => 'Kapazität'],
            ['code' => 'hardwareFailure', 'label' => 'Hardwareausfall'],
            ['code' => 'dependency', 'label' => 'Abhängigkeit'],
            ['code' => 'externalProvider', 'label' => 'Externer Anbieter'],
        ],
        'result' => [
            ['code' => 'resolved', 'label' => 'Gelöst'],
            ['code' => 'workaround', 'label' => 'Workaround'],
            ['code' => 'knownIssue', 'label' => 'Known Issue'],
            ['code' => 'deferred', 'label' => 'Verschoben'],
            ['code' => 'escalated', 'label' => 'Eskaliert'],
        ],
        'product_group' => [
            ['code' => 'router', 'label' => 'Router'],
            ['code' => 'switch', 'label' => 'Switch'],
            ['code' => 'firewall', 'label' => 'Firewall'],
            ['code' => 'accessPoint', 'label' => 'Access Point'],
            ['code' => 'server', 'label' => 'Server'],
            ['code' => 'workstation', 'label' => 'Workstation'],
            ['code' => 'printer', 'label' => 'Printer'],
            ['code' => 'virtualization', 'label' => 'Virtualisierung'],
            ['code' => 'saas', 'label' => 'SaaS'],
        ],
    ],
    'classification_requirements' => [
        [
            'entry_type_code' => 'change',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'change',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
            'only_if_json' => ['priority' => ['high', 'critical']],
        ],
        [
            'entry_type_code' => 'change',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'change',
            'required_domain' => 'root_cause',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'incident',
            'required_domain' => 'priority',
            'enforce_phase' => ClassificationRequirementPhase::OnCreate->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'incident',
            'required_domain' => 'result',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Hard->value,
            'min_count' => 1,
        ],
        [
            'entry_type_code' => 'incident',
            'required_domain' => 'defect_type',
            'enforce_phase' => ClassificationRequirementPhase::BeforeComplete->value,
            'severity' => ClassificationRequirementSeverity::Soft->value,
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
    ],
    'procedure_templates' => [
        ['code' => 'IT_FW_UPDATE'],
        ['code' => 'IT_PATCH_DEPLOY'],
        ['code' => 'IT_INCIDENT_TRIAGE'],
        ['code' => 'IT_NEW_CLIENT_ONBOARD'],
        ['code' => 'IT_OFFBOARD_USER'],
        ['code' => 'IT_BACKUP_RESTORE_TEST'],
        ['code' => 'IT_NETWORK_CHANGE'],
    ],
    'protocol_templates' => [
        ['code' => 'IT_CHANGE_PROTOCOL'],
        ['code' => 'IT_INCIDENT_REPORT'],
        ['code' => 'IT_HANDOVER_DEVICE'],
        ['code' => 'IT_MAINTENANCE_LOG'],
        ['code' => 'IT_SECURITY_INCIDENT'],
    ],
    'asset_categories' => [
        'router',
        'switch',
        'firewall',
        'accessPoint',
        'server',
        'workstation',
        'notebook',
        'printer',
        'vm',
        'saasAccount',
        'monitor',
        'phone',
    ],
    'tags_seed' => [
        '#after-hours',
        '#oncall',
        '#critical-customer',
        '#prod',
        '#dev',
        '#test',
        '#emergency-change',
        '#planned-change',
    ],
];
