<?php
/*
 * Created on   : Wed Jun 03 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : it.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use App\Enums\Classification\{ClassificationRequirementPhase, ClassificationRequirementSeverity};

return [
    'code' => 'it',
    'label' => 'IT-Service / Managed Services',
    'description' => 'IT-Service und Managed Services: Incidents, Requests, Changes und Wartung mit Softwarekatalog, Raumanforderungen und Datenschutz-Vorlagen.',
    // v2 (Feature 100): Entsorgungs-Modul empfohlen + AVV-Presets für
    // Datenträger/Batterien (Altgeräte-Rücknahme beim Kunden).
    // v3: Default-Eintragstypen (Struktur-Typen) ans Profil gekoppelt.
    'version' => 3,
    // Vorschlag für den Standard-Arbeitsbereich (MVP-840); nur Default, kein Zwang.
    'nav_focus_default' => 'service',
    // Default-Struktur-Typen (EntryTypeSeeder::profiles()) — nicht die
    // Classification-Domäne entry_type weiter unten.
    'entry_type_defaults' => ['general', 'service', 'it_ticket'],
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
        'module.helpdesk',
        'module.kanban',
        'module.agile_projects',
        'module.contracts',
        'module.lager',
        'module.entsorgung',
    ],
    'classifications' => [
        // Feature 100: IT-typische Ergänzungen zu den AVV-Plattform-Defaults.
        'waste_code' => [
            ['code' => 'avv_168001', 'label' => '16 80 01 — Magnetische und optische Datenträger'],
            ['code' => 'avv_200133_h', 'label' => '20 01 33* — Gemischte Batterien (mit gefährlichen)'],
            ['code' => 'avv_200134', 'label' => '20 01 34 — Batterien (nicht gefährlich)'],
        ],
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
        [
            'code' => 'IT_NETWORK_CHANGE',
            'name' => 'Kritische Netzwerk-/Konfigurationsänderung',
            'domain' => 'it',
            'risk_level' => 'high',
            'description' => 'Change mit Konfigurationsbackup, Vier-Augen-Freigabe und Funktionstest.',
            'steps' => [
                ['code' => 'backup', 'step_type' => 'backup', 'label' => 'Konfigurationsbackup vor Änderung', 'requires_proof_type' => 'backup'],
                ['code' => 'changedoku', 'step_type' => 'text', 'label' => 'Geplante Änderung dokumentieren'],
                ['code' => 'freigabe', 'step_type' => 'freigabe', 'label' => 'Vier-Augen-Freigabe einholen', 'requires_second_person' => true],
                ['code' => 'umsetzung', 'step_type' => 'confirm', 'label' => 'Änderung umsetzen'],
                ['code' => 'funktionstest', 'step_type' => 'link_test', 'label' => 'Funktionstest durchführen'],
                ['code' => 'rollbackVorbereitet', 'step_type' => 'confirm', 'label' => 'Rollback-Pfad bestätigen', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'IT_BACKUP_RESTORE_TEST',
            'name' => 'Backup-Restore-Test',
            'domain' => 'it',
            'risk_level' => 'normal',
            'description' => 'Wiederherstellbarkeit eines Backups prüfen und nachweisen.',
            'steps' => [
                ['code' => 'auswahl', 'step_type' => 'text', 'label' => 'Backup-Satz auswählen'],
                ['code' => 'restore', 'step_type' => 'confirm', 'label' => 'Test-Restore durchführen'],
                ['code' => 'integritaet', 'step_type' => 'confirm', 'label' => 'Integrität/Funktion prüfen'],
                ['code' => 'nachweis', 'step_type' => 'file', 'label' => 'Restore-Nachweis ablegen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'file'],
            ],
        ],
        [
            'code' => 'IT_FW_UPDATE',
            'name' => 'Firewall-Update',
            'domain' => 'it',
            'risk_level' => 'high',
            'description' => 'Firmware-/Regelwerk-Update mit Wartungsfenster, Backup, Vier-Augen-Freigabe und Funktionstest.',
            'steps' => [
                ['code' => 'wartungsfenster', 'step_type' => 'confirm', 'label' => 'Wartungsfenster abgestimmt und angekündigt'],
                ['code' => 'backup', 'step_type' => 'backup', 'label' => 'Konfigurationsbackup vor dem Update', 'requires_proof_type' => 'backup'],
                ['code' => 'releasenotes', 'step_type' => 'confirm', 'label' => 'Release Notes/Kompatibilität geprüft'],
                ['code' => 'freigabe', 'step_type' => 'freigabe', 'label' => 'Vier-Augen-Freigabe einholen', 'requires_second_person' => true],
                ['code' => 'update', 'step_type' => 'confirm', 'label' => 'Update einspielen'],
                ['code' => 'test', 'step_type' => 'link_test', 'label' => 'Regeln, VPN und Erreichbarkeit testen'],
                ['code' => 'rollback', 'step_type' => 'confirm', 'label' => 'Rollback-Pfad bestätigen', 'required' => false, 'blocking' => false],
            ],
        ],
        [
            'code' => 'IT_PATCH_DEPLOY',
            'name' => 'Patch-Rollout',
            'domain' => 'it',
            'risk_level' => 'normal',
            'description' => 'Patches auf Pilotgruppe testen, ausrollen und Fehlschläge nachziehen.',
            'steps' => [
                ['code' => 'patchliste', 'step_type' => 'text', 'label' => 'Patches und betroffene Systeme erfassen'],
                ['code' => 'pilot', 'step_type' => 'confirm', 'label' => 'Test auf Pilotgruppe durchgeführt'],
                ['code' => 'rollout', 'step_type' => 'confirm', 'label' => 'Rollout auf alle Systeme'],
                ['code' => 'fehler', 'step_type' => 'text', 'label' => 'Fehlgeschlagene Systeme festhalten', 'required' => false, 'blocking' => false],
                ['code' => 'nachweis', 'step_type' => 'file', 'label' => 'Rollout-Bericht ablegen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'file'],
            ],
        ],
        [
            'code' => 'IT_INCIDENT_TRIAGE',
            'name' => 'Incident-Triage',
            'domain' => 'it',
            'risk_level' => 'normal',
            'description' => 'Störung aufnehmen, Auswirkung einstufen, Sofortmaßnahme, Zuweisung und Kommunikation.',
            'steps' => [
                ['code' => 'aufnahme', 'step_type' => 'text', 'label' => 'Störung/Meldung aufnehmen'],
                ['code' => 'auswirkung', 'step_type' => 'choice', 'label' => 'Auswirkung und Priorität einstufen'],
                ['code' => 'sofortmassnahme', 'step_type' => 'confirm', 'label' => 'Sofortmaßnahme/Workaround gesetzt', 'required' => false, 'blocking' => false],
                ['code' => 'zuweisung', 'step_type' => 'confirm', 'label' => 'Zuweisung oder Eskalation'],
                ['code' => 'kommunikation', 'step_type' => 'confirm', 'label' => 'Betroffene informieren'],
            ],
        ],
        [
            'code' => 'IT_NEW_CLIENT_ONBOARD',
            'name' => 'Client einrichten',
            'domain' => 'it',
            'risk_level' => 'normal',
            'description' => 'Neuen Arbeitsplatzrechner inventarisieren, installieren, absichern und übergeben.',
            'steps' => [
                ['code' => 'inventar', 'step_type' => 'text', 'label' => 'Gerät und Seriennummer inventarisieren'],
                ['code' => 'image', 'step_type' => 'confirm', 'label' => 'Betriebssystem/Standardimage installieren'],
                ['code' => 'sicherheit', 'step_type' => 'confirm', 'label' => 'Verschlüsselung, Endpoint-Schutz und Updates aktiv'],
                ['code' => 'konten', 'step_type' => 'confirm', 'label' => 'Benutzerkonto und Domänenbeitritt'],
                ['code' => 'uebergabe', 'step_type' => 'signature', 'label' => 'Übergabe an die Nutzerin/den Nutzer', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'signature'],
            ],
        ],
        [
            'code' => 'IT_OFFBOARD_USER',
            'name' => 'Benutzer offboarden',
            'domain' => 'it',
            'risk_level' => 'high',
            'description' => 'Konten sperren, Geräte einziehen, Daten übergeben, Berechtigungen entziehen — mit Vier-Augen-Bestätigung.',
            'steps' => [
                ['code' => 'sperren', 'step_type' => 'confirm', 'label' => 'Konten sperren (Verzeichnis, Cloud, VPN)'],
                ['code' => 'geraete', 'step_type' => 'dienstmittel', 'label' => 'Geräte und Token einziehen'],
                ['code' => 'daten', 'step_type' => 'confirm', 'label' => 'Postfach/Daten übergeben oder archivieren'],
                ['code' => 'zugriffe', 'step_type' => 'confirm', 'label' => 'Berechtigungen und Freigaben entziehen'],
                ['code' => 'vieraugen', 'step_type' => 'confirm', 'label' => 'Vollständigkeit im Vier-Augen-Prinzip bestätigen', 'requires_second_person' => true],
                ['code' => 'nachweis', 'step_type' => 'file', 'label' => 'Offboarding-Nachweis ablegen', 'required' => false, 'blocking' => false, 'requires_proof_type' => 'file'],
            ],
        ],
    ],
    'room_requirement_templates_seed' => [
        ['code' => 'it_inventar', 'kind' => 'itInventory', 'label' => 'IT-Inventar im Raum', 'note' => 'Clients, Drucker, Netzwerkdosen, Access Points dem Raum zuordnen.'],
        ['code' => 'it_serverraum', 'kind' => 'accessRestriction', 'label' => 'Serverraum/Technik zutrittsbeschränkt', 'level' => 'admin'],
        ['code' => 'it_pruefung', 'kind' => 'technicalInspection', 'label' => 'Technische Prüfung (USV/Klima)', 'level' => 'jährlich'],
    ],
    // HINWEIS: 'protocol_templates' und 'asset_categories' werden vom
    // BranchProfileInstaller NICHT installiert (kein ProtocolTemplate-Modell;
    // Asset-Kategorien stammen aus config('asset_categories')). Sie dienen als
    // Branchen-Taxonomie/Vorlage für künftige Features.
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
    // Qualifikationen/Unterweisungen je Gewerk (Vollaudit 2026-07, N13).
    'qualifications_seed' => [
        ['name' => 'Datenschutz-Unterweisung (DSGVO)', 'abbreviation' => 'DSGVO-U', 'description' => 'Jährliche Datenschutz-Unterweisung für Mitarbeitende mit Kundendatenzugriff.'],
        ['name' => 'IT-Sicherheits-Awareness-Schulung', 'abbreviation' => 'SecAware', 'description' => 'Regelmäßige Security-Awareness-Schulung (Phishing, Passworthygiene, Meldewege).'],
    ],
    'software_seed' => [
        // Betriebssysteme
        ['name' => 'Windows 11 Pro', 'vendor' => 'Microsoft', 'kind' => 'operating_system', 'license_type' => 'oem', 'default_version' => '23H2'],
        ['name' => 'Windows Server 2025', 'vendor' => 'Microsoft', 'kind' => 'operating_system', 'license_type' => 'perpetual', 'default_version' => '2025'],
        ['name' => 'Ubuntu Server LTS', 'vendor' => 'Canonical', 'kind' => 'operating_system', 'license_type' => 'open_source', 'default_version' => '24.04'],
        ['name' => 'macOS', 'vendor' => 'Apple', 'kind' => 'operating_system', 'license_type' => 'oem', 'default_version' => 'Sequoia'],
        // Anwendungen
        ['name' => 'Microsoft 365 Business Standard', 'vendor' => 'Microsoft', 'kind' => 'application', 'license_type' => 'subscription'],
        ['name' => 'Adobe Acrobat Pro', 'vendor' => 'Adobe', 'kind' => 'application', 'license_type' => 'subscription'],
        ['name' => 'Sophos Endpoint', 'vendor' => 'Sophos', 'kind' => 'application', 'license_type' => 'subscription'],
        ['name' => 'Veeam Backup & Replication', 'vendor' => 'Veeam', 'kind' => 'application', 'license_type' => 'subscription'],
        ['name' => 'Visual Studio Code', 'vendor' => 'Microsoft', 'kind' => 'application', 'license_type' => 'open_source'],
    ],
    // Datenschutz-Anforderungsvorlagen (Nachtrag 043c): AV-lastiges Gewerk →
    // Nachweispflege der TOM aktiv.
    'dataprotection_requirements_seed' => [
        ['key' => 'avv_required'],
        ['key' => 'avv_current'],
        ['key' => 'gvv_required'],
        ['key' => 'dpia_required'],
        ['key' => 'tom_assigned'],
        ['key' => 'tom_proof_current'],
    ],
    // Vertragsvorlagen (MVP-893): Laufzeit, Kündigung, Pflichten relativ zum Beginn.
    'contract_templates' => [
        [
            'name' => 'Wartungs- und Supportvertrag',
            'kind' => 'maintenance',
            'title' => 'IT-Wartung und Support',
            'term_kind' => 'fixed',
            'min_term_months' => 12,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 90,
            'value_period' => 'monthly',
            'obligations' => [
                ['kind' => 'review', 'title' => 'Jahresgespräch und Leistungsbericht', 'offset_months' => 11, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
        [
            'name' => 'Softwarelizenz',
            'kind' => 'license',
            'title' => 'Softwarelizenz',
            'term_kind' => 'fixed',
            'min_term_months' => 12,
            'auto_renew' => true,
            'renew_period_months' => 12,
            'notice_period_days' => 30,
            'value_period' => 'yearly',
            'obligations' => [
                ['kind' => 'renewal_warning', 'title' => 'Lizenzbedarf prüfen', 'offset_months' => 10, 'recurring' => true, 'recurrence_months' => 12],
            ],
        ],
    ],
];
