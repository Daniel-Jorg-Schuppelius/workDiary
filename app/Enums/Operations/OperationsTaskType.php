<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OperationsTaskType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Operations;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\Notification\NotificationEvent;

/**
 * Typ einer Betriebsaufgabe (Feature 041, MVP-058). Jeder Typ mappt
 * auf sein Benachrichtigungs-Ereignis — die Regeln (Kanäle, Drosselung,
 * Eskalation) pflegt der Admin weiterhin zentral in den
 * Benachrichtigungsregeln, keine zweite Regel-Engine.
 */
enum OperationsTaskType: string implements HasLabel {
    use HasOptions;

    case BackupOverdue = 'backup_overdue';
    case BackupFailed = 'backup_failed';
    case RestoreTestOverdue = 'restore_test_overdue';
    case UpdateAvailable = 'update_available';
    case UpdateSecurity = 'update_security';
    case LicenseExpiring = 'license_expiring';
    case LicenseLimitNear = 'license_limit_near';
    case CredentialExpiring = 'credential_expiring';
    case ConnectionFailing = 'connection_failing';
    case ComponentEol = 'component_eol';
    case PluginDisabled = 'plugin_disabled';
    case SchedulerOverdue = 'scheduler_overdue';
    case MaintenanceScheduled = 'maintenance_scheduled';
    case ConfigMissing = 'config_missing';
    case SupportGrantOpen = 'support_grant_open';
    case ProblemReportOpen = 'problem_report_open';
    // Cloud-Dokumenteingang (Feature 080 P9; Audit 2026-08, W4.4).
    case CloudIntakeReauth = 'cloud_intake_reauth';
    case CloudIntakeQuarantined = 'cloud_intake_quarantined';

    public function label(): string {
        return __('operations.type.' . $this->value);
    }

    public function notificationEvent(): ?NotificationEvent {
        return match ($this) {
            self::BackupOverdue => NotificationEvent::OperationsBackupOverdue,
            self::BackupFailed => NotificationEvent::OperationsBackupFailed,
            self::RestoreTestOverdue => NotificationEvent::OperationsRestoreTestOverdue,
            self::UpdateAvailable => NotificationEvent::OperationsUpdateAvailable,
            self::UpdateSecurity => NotificationEvent::OperationsUpdateSecurity,
            self::LicenseExpiring => NotificationEvent::OperationsLicenseExpiring,
            self::CredentialExpiring => NotificationEvent::OperationsCredentialExpiring,
            self::ConnectionFailing => NotificationEvent::OperationsConnectionFailing,
            self::ComponentEol => NotificationEvent::OperationsComponentEol,
            self::PluginDisabled => NotificationEvent::OperationsPluginDisabled,
            self::SchedulerOverdue => NotificationEvent::OperationsSchedulerOverdue,
            self::MaintenanceScheduled => NotificationEvent::OperationsMaintenanceScheduled,
            self::ProblemReportOpen => NotificationEvent::OperationsProblemReportReceived,
            self::CloudIntakeReauth => NotificationEvent::OperationsCloudIntakeReauth,
            self::CloudIntakeQuarantined => NotificationEvent::OperationsCloudIntakeQuarantined,
            // Fehlende Konfiguration/offene Supportfreigaben sind reine
            // Aufgaben (Onboarding-/Grant-UI benachrichtigt bereits selbst);
            // die Limit-Warnung (N9) ist ebenfalls eine reine Betriebsaufgabe.
            self::ConfigMissing, self::SupportGrantOpen, self::LicenseLimitNear => null,
        };
    }

    public function icon(): string {
        return $this->notificationEvent()?->icon() ?? match ($this) {
            self::ConfigMissing => 'settings_alert',
            self::SupportGrantOpen => 'support_agent',
            default => 'task_alt',
        };
    }
}
