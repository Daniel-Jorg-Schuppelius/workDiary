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

use App\Enums\Notification\NotificationEvent;

/**
 * Typ einer Betriebsaufgabe (Feature 041, MVP-058). Jeder Typ mappt
 * auf sein Benachrichtigungs-Ereignis — die Regeln (Kanäle, Drosselung,
 * Eskalation) pflegt der Admin weiterhin zentral in den
 * Benachrichtigungsregeln, keine zweite Regel-Engine.
 */
enum OperationsTaskType: string {
    case BackupOverdue = 'backup_overdue';
    case BackupFailed = 'backup_failed';
    case RestoreTestOverdue = 'restore_test_overdue';
    case UpdateAvailable = 'update_available';
    case UpdateSecurity = 'update_security';
    case LicenseExpiring = 'license_expiring';
    case CredentialExpiring = 'credential_expiring';
    case ConnectionFailing = 'connection_failing';
    case ComponentEol = 'component_eol';
    case PluginDisabled = 'plugin_disabled';
    case SchedulerOverdue = 'scheduler_overdue';
    case MaintenanceScheduled = 'maintenance_scheduled';
    case ConfigMissing = 'config_missing';
    case SupportGrantOpen = 'support_grant_open';
    case ProblemReportOpen = 'problem_report_open';

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
            // Fehlende Konfiguration/offene Supportfreigaben sind reine
            // Aufgaben (Onboarding-/Grant-UI benachrichtigt bereits selbst).
            self::ConfigMissing, self::SupportGrantOpen => null,
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
