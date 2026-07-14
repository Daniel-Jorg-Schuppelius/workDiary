<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotificationEvent.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Notification;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use App\Enums\User\UserRole;

/**
 * Ereignistypen-Registry für Benachrichtigungen & Eskalationen (MVP-018).
 *
 * Jeder Case ist im Code real verdrahtet — entweder synchron (Service-Trigger)
 * oder über den Fristen-Scanner (notifications:scan-deadlines). Die
 * default*()-Methoden liefern die Seed-Defaults, die greifen, solange eine
 * Organisation keine eigene NotificationRule-Zeile angelegt hat.
 */
enum NotificationEvent: string implements HasLabel {
    use HasOptions;

    /** Synchron: OpenIssueService::assign()/create() */
    case OpenIssueAssigned = 'openIssue.assigned';
    /** Scanner: OpenIssue mit due_at innerhalb des Vorlaufs */
    case OpenIssueDueSoon = 'openIssue.dueSoon';
    /** Scanner: OpenIssue mit überschrittener due_at */
    case OpenIssueOverdue = 'openIssue.overdue';
    /** Scanner: Kommunikationsnotiz mit fälliger Folgeaktion */
    case CommunicationFollowupDueSoon = 'communication.followupDueSoon';
    /** Scanner: Kommunikationsnotiz mit überfälliger Folgeaktion */
    case CommunicationFollowupOverdue = 'communication.followupOverdue';
    /** Scanner: Dokument läuft innerhalb des Vorlaufs ab */
    case DocumentExpiringSoon = 'document.expiringSoon';
    /** Scanner: Dokument ist abgelaufen */
    case DocumentExpired = 'document.expired';
    /** Synchron: TimeCorrectionService::submit() */
    case TimeCorrectionRequested = 'timeCorrection.requested';
    /** Synchron: TimeCorrectionService::approve()/reject() */
    case TimeCorrectionDecided = 'timeCorrection.decided';
    /** Synchron: MonthClosureService::submit() */
    case MonthClosureSubmitted = 'monthClosure.submitted';
    /** Scanner: ISMS-Zertifikat läuft innerhalb des Vorlaufs (30 Tage) ab */
    case IsmsCertificateExpiring = 'isms.certificateExpiring';
    /** Scanner: ISMS-Korrekturmaßnahme mit überschrittener Fälligkeit (open/inProgress) */
    case IsmsCorrectiveActionOverdue = 'isms.correctiveActionOverdue';
    /** Scanner: jüngste freigegebene Netto-Risikobewertung mit valid_until innerhalb des Vorlaufs (30 Tage) bzw. überschritten */
    case IsmsRiskReviewDue = 'isms.riskReviewDue';
    /** Scanner: ISMS-Schwachstelle mit überschrittener Frist (due_on, Status open/underReview/mitigating) */
    case IsmsVulnerabilityOverdue = 'isms.vulnerabilityOverdue';
    /** Synchron: neuer kritischer ISMS-Sicherheitsvorfall (SecurityIncidentService::create) */
    case IsmsIncidentCritical = 'isms.incidentCritical';
    /** Scanner: ISMS-Lieferantenbewertung mit überschrittenem Review (next_review_on, nicht freigegeben) */
    case IsmsSupplierReviewOverdue = 'isms.supplierReviewOverdue';
    /** Scanner: Service-Ticket mit SLA-Restzeit unter dem Schwellwert (gefährdet) */
    case SlaAtRisk = 'sla.atRisk';
    /** Scanner: Service-Ticket mit überschrittener SLA-Frist (verletzt) */
    case SlaBreached = 'sla.breached';
    /** Scanner: SLA-Inklusivzeit-Kontingent eines Vertrags erreicht die Warnschwelle (Feature 010 → Rang 44) */
    case SlaQuotaWarning = 'sla.quotaWarning';
    /** Scanner: Asset-Ausgabe mit überschrittener erwarteter Rückgabe (Feature 009) */
    case AssetReturnOverdue = 'asset.returnOverdue';
    /** Scanner: Wartungs-/Prüfplan fällig innerhalb des Vorlaufs (Feature 009) */
    case MaintenanceDueSoon = 'maintenance.dueSoon';
    /** Scanner: Wartungs-/Prüfplan mit überschrittener Fälligkeit (Feature 009) */
    case MaintenanceOverdue = 'maintenance.overdue';
    /** Synchron: neues kritisches Sicherheitsereignis (Unfall/critical) — SafetyEventService::create (Feature 013) */
    case SafetyCriticalEvent = 'safety.criticalEvent';
    /** Scanner: Mitarbeiter-Qualifikation/Unterweisung läuft innerhalb des Vorlaufs (30 Tage) ab (Feature 013) */
    case QualificationExpiring = 'qualification.expiring';
    /** Synchron: Schichttausch beantragt (Feature 007) — an Ziel-Kollegen bzw. Teamleitung */
    case ShiftExchangeRequested = 'shiftExchange.requested';
    /** Synchron: Schichttausch freigegeben/abgelehnt (Feature 007) — an Antragsteller */
    case ShiftExchangeDecided = 'shiftExchange.decided';
    /** Synchron: Kunde stellt über Portal/Token eine Rückfrage (Feature 012) — an Verantwortlichen/Teamleitung */
    case CustomerQueryRaised = 'customer.queryRaised';

    case IdeaMapShared = 'ideaMap.shared';

    /** Zustellproblem einer Sendung (Feature 059, MVP-128). */
    case ShipmentDeliveryProblem = 'shipment.deliveryProblem';

    /** Synchron: eingehender Anruf auf die Opt-in-Durchwahl eines Mitarbeiters (Feature 056, MVP-118) — gezielt an genau diese Person. */
    case CtiIncomingCall = 'cti.incomingCall';
        // Helpdesk (Feature 065, P3) — sla.atRisk/breached existieren bereits.
    case TicketAssigned = 'ticket.assigned';
    case TicketCustomerReplied = 'ticket.customerReplied';
    case TicketWaitingExpired = 'ticket.waitingExpired';
    /** Scanner: Wirksamkeitsprüfung eines gelösten Problems/Known Errors überfällig (Feature 065, MVP-156). */
    case ProblemEffectivenessDue = 'problem.effectivenessDue';

        // Betriebsereignisse (Feature 041, MVP-053–058): Quellen melden über
        // den OperationsAlertService — Empfänger sind Adminrollen, nie die
        // „betroffene Person". Routine (updateAvailable) ist drosselbar,
        // Security-Hinweise bleiben in Diagnose/Komponenten immer sichtbar.
    // Feature 070: Krisenalarm an den Krisenstab (überstimmt Ruhezeiten, D7).
    case CrisisAlert = 'crisis.alert';

    // Feature 072: Fristeneskalation überfälliger Reklamationen (MVP-255).
    case ClaimEscalation = 'claim.escalation';

    // Feature 073: überfällige Verleih-Rückgabe (MVP-264).
    case RentalReturnOverdue = 'rental.returnOverdue';

    // Feature 074: Leasing-/Vertragsfrist wird fällig (MVP-273/278).
    case AssetFinanceDeadline = 'assetFinance.deadline';

    // Welle D (CLM): allgemeine Vertragsfrist/-obligation wird fällig.
    case ContractDeadlineDue = 'contract.deadlineDue';

    // Feature 075: Prüfung fällig/überfällig (MVP-285/288).
    case AssetInspectionDue = 'assetCompliance.inspectionDue';

    case OperationsBackupOverdue = 'operations.backupOverdue';
    case OperationsBackupFailed = 'operations.backupFailed';
    case OperationsRestoreTestOverdue = 'operations.restoreTestOverdue';
    case OperationsUpdateAvailable = 'operations.updateAvailable';
    case OperationsUpdateSecurity = 'operations.updateSecurity';
    case OperationsLicenseExpiring = 'operations.licenseExpiring';
    case OperationsCredentialExpiring = 'operations.credentialExpiring';
    case OperationsConnectionFailing = 'operations.connectionFailing';
    case OperationsComponentEol = 'operations.componentEol';
    case OperationsPluginDisabled = 'operations.pluginDisabled';
    case OperationsSchedulerOverdue = 'operations.schedulerOverdue';
    case OperationsMaintenanceScheduled = 'operations.maintenanceScheduled';
    case OperationsProblemReportReceived = 'operations.problemReportReceived';

    public function label(): string {
        return (string) __('enums.notification.event.' . $this->value);
    }

    /**
     * Default-Kanäle, solange keine Org-Regel existiert.
     *
     * @return list<string>
     */
    public function defaultChannels(): array {
        return [NotificationChannel::InApp->value, NotificationChannel::Mail->value];
    }

    /**
     * Geht die Benachrichtigung per Default an die betroffene Person?
     * Bei Workflow-Anträgen (requested/submitted) ist die betroffene Person
     * der Antragsteller selbst — Empfänger sind hier die Entscheider (Rolle).
     */
    public function defaultNotifyAffected(): bool {
        // Betriebsereignisse haben keine „betroffene Person" — nur Rollen.
        if (str_starts_with($this->value, 'operations.')) {
            return false;
        }

        return ! in_array($this, [self::TimeCorrectionRequested, self::MonthClosureSubmitted, self::IsmsCertificateExpiring, self::IsmsIncidentCritical, self::SafetyCriticalEvent, self::ShiftExchangeRequested, self::CustomerQueryRaised, self::ShipmentDeliveryProblem, self::SlaQuotaWarning], true);
    }

    /**
     * Default-Empfängerrollen (zusätzlich bzw. statt der betroffenen Person).
     *
     * @return list<string>
     */
    public function defaultRecipientRoles(): array {
        // Betriebsereignisse gehen per Default an die Admin-Rolle der Org
        // (bei Selbst-Hosting zugleich Betreiber); MVP-058 führt sie
        // zusätzlich als Aufgabe im Admin-Aufgabencenter.
        if (str_starts_with($this->value, 'operations.')) {
            return [UserRole::Admin->value];
        }

        return match ($this) {
            self::TimeCorrectionRequested,
            self::MonthClosureSubmitted => [UserRole::Teamleitung->value],
            // Zertifikatsablauf betrifft keine einzelne Person — Default an
            // die Leitungs-/Admin-Rollen der Organisation.
            self::IsmsCertificateExpiring => [UserRole::Teamleitung->value, UserRole::Admin->value],
            // Überfällige Korrekturmaßnahme: primär der Verantwortliche
            // (notify_affected), die Teamleitung als Fallback/Mitwisser.
            self::IsmsCorrectiveActionOverdue => [UserRole::Teamleitung->value],
            // Fälliges Risiko-Review: primär der Risikoeigentümer
            // (notify_affected), die Teamleitung als Fallback/Mitwisser.
            self::IsmsRiskReviewDue => [UserRole::Teamleitung->value],
            // Überfällige Schwachstelle: primär der Verantwortliche
            // (notify_affected), die Teamleitung als Fallback/Mitwisser.
            self::IsmsVulnerabilityOverdue => [UserRole::Teamleitung->value],
            // Überfällige Lieferanten-Review: primär der Verantwortliche
            // (notify_affected), die Teamleitung als Fallback/Mitwisser.
            self::IsmsSupplierReviewOverdue => [UserRole::Teamleitung->value],
            // Kritischer Vorfall: betrifft keine einzelne Person — Default an
            // die Leitungs-/Admin-Rollen der Organisation.
            self::IsmsIncidentCritical => [UserRole::Teamleitung->value, UserRole::Admin->value],
            // SLA-Risiko/-Verletzung: primär der Ticket-Verantwortliche
            // (notify_affected), die Teamleitung als Fallback/Eskalationskette.
            self::SlaAtRisk,
            self::SlaBreached => [UserRole::Teamleitung->value],
            // Kontingent-Warnung (Rang 44): betrifft keine Einzelperson —
            // Default an die Teamleitung (Vertrags-/Auftragssteuerung).
            self::SlaQuotaWarning => [UserRole::Teamleitung->value],
            // Überfällige Asset-Rückgabe: primär die ausleihende Person
            // (notify_affected), Fallback/Eskalationskette die Teamleitung.
            self::AssetReturnOverdue => [UserRole::Teamleitung->value],
            // Wartungs-/Prüffälligkeit (MVP-336): primär der Asset-
            // Verantwortliche (notify_affected = aktueller Ausgabe-Inhaber),
            // die Teamleitung als Fallback/Mitwisser; die Überfälligkeit
            // eskaliert zusätzlich über die Eskalationsleiter (MVP-331).
            self::MaintenanceDueSoon,
            self::MaintenanceOverdue => [UserRole::Teamleitung->value],
            // Kritisches Sicherheitsereignis: betrifft keine einzelne Person —
            // synchron an die Leitungs-/Admin-Rollen der Organisation.
            self::SafetyCriticalEvent => [UserRole::Teamleitung->value, UserRole::Admin->value],
            // Ablaufende Qualifikation/Unterweisung: primär die betroffene
            // Person (notify_affected), Default-Fallback die Teamleitung.
            self::QualificationExpiring => [UserRole::Teamleitung->value],
            // Schichttausch-Antrag (Feature 007): Empfänger sind die Teamleitung
            // (Freigabe) zusätzlich zum optionalen Ziel-Kollegen (separat im
            // Service adressiert). Bei der Entscheidung wird der Antragsteller
            // über notify_affected erreicht.
            self::ShiftExchangeRequested => [UserRole::Teamleitung->value],
            // Kunden-Rückfrage (Feature 012): betrifft keinen einzelnen
            // Mitarbeiter — Default an die Leitung zur Bearbeitung.
            self::CustomerQueryRaised => [UserRole::Teamleitung->value],
            // Zustellproblem (Feature 059): betrifft keine Einzelperson —
            // Default an die Leitung, die die Sendung/Auslieferung verantwortet.
            self::ShipmentDeliveryProblem => [UserRole::Teamleitung->value],
            // Überfällige Verleih-Rückgabe (Feature 073): an die Leitung —
            // der Akten-Verantwortliche wird im Service separat adressiert.
            self::RentalReturnOverdue => [UserRole::Teamleitung->value],
            // Leasingfristen (Feature 074): Vertrags-/Fristensteuerung ist
            // Leitungsaufgabe; der Verantwortliche der Akte via Service.
            self::AssetFinanceDeadline => [UserRole::Teamleitung->value],
            // Allgemeine Vertragsfristen (Welle D, CLM): Vertragssteuerung ist
            // Leitungsaufgabe; der Verantwortliche der Obligation via Service.
            self::ContractDeadlineDue => [UserRole::Teamleitung->value],
            // Prüffälligkeit (Feature 075): betrifft keine Einzelperson —
            // an die Teamleitung (Prüfmittelverantwortung).
            self::AssetInspectionDue => [UserRole::Teamleitung->value],
            // Fällige Wirksamkeitsprüfung (Feature 065, MVP-156): primär der
            // Problem-Owner (notify_affected), Teamleitung als Fallback.
            self::ProblemEffectivenessDue => [UserRole::Teamleitung->value],
            default => [],
        };
    }

    /** Material-Symbols-Icon für In-App-/Push-Darstellung. */
    public function icon(): string {
        return match ($this) {
            self::OpenIssueAssigned,
            self::OpenIssueDueSoon,
            self::OpenIssueOverdue => 'assignment_late',
            self::CommunicationFollowupDueSoon,
            self::CommunicationFollowupOverdue => 'forward_to_inbox',
            self::DocumentExpiringSoon,
            self::DocumentExpired => 'folder_open',
            self::TimeCorrectionRequested,
            self::TimeCorrectionDecided => 'edit_calendar',
            self::MonthClosureSubmitted => 'event_available',
            self::IsmsCertificateExpiring => 'workspace_premium',
            self::IsmsCorrectiveActionOverdue => 'fact_check',
            self::IsmsRiskReviewDue => 'crisis_alert',
            self::IsmsVulnerabilityOverdue => 'bug_report',
            self::IsmsSupplierReviewOverdue => 'handshake',
            self::IsmsIncidentCritical => 'report',
            self::SlaAtRisk => 'timer',
            self::SlaBreached => 'timer_off',
            self::SlaQuotaWarning => 'data_usage',
            self::AssetReturnOverdue => 'assignment_return',
            self::MaintenanceDueSoon,
            self::MaintenanceOverdue => 'handyman',
            self::SafetyCriticalEvent => 'e911_emergency',
            self::CrisisAlert => 'emergency_home',
            self::ClaimEscalation => 'assignment_late',
            self::RentalReturnOverdue => 'forklift',
            self::AssetFinanceDeadline => 'request_quote',
            self::ContractDeadlineDue => 'contract',
            self::AssetInspectionDue => 'rule_settings',
            self::QualificationExpiring => 'workspace_premium',
            self::ShiftExchangeRequested,
            self::ShiftExchangeDecided => 'swap_horiz',
            self::CustomerQueryRaised => 'contact_support',
            // Karten-Freigabe (Feature 054): Payload bewusst nur Titel + Link —
            // die IdeaMapPolicy greift beim Klick.
            self::IdeaMapShared => 'emoji_objects',
            self::ShipmentDeliveryProblem => 'local_shipping',
            self::CtiIncomingCall => 'ring_volume',
            self::TicketAssigned => 'confirmation_number',
            self::TicketCustomerReplied => 'mark_email_unread',
            self::TicketWaitingExpired => 'alarm',
            self::ProblemEffectivenessDue => 'troubleshoot',
            self::OperationsBackupOverdue,
            self::OperationsBackupFailed => 'backup',
            self::OperationsRestoreTestOverdue => 'settings_backup_restore',
            self::OperationsUpdateAvailable => 'system_update_alt',
            self::OperationsUpdateSecurity => 'security_update_warning',
            self::OperationsLicenseExpiring => 'key',
            self::OperationsCredentialExpiring => 'password',
            self::OperationsConnectionFailing => 'link_off',
            self::OperationsComponentEol => 'inventory_2',
            self::OperationsPluginDisabled => 'extension_off',
            self::OperationsSchedulerOverdue => 'schedule',
            self::OperationsMaintenanceScheduled => 'engineering',
            self::OperationsProblemReportReceived => 'flag',
        };
    }

    /**
     * Nur für „überfällig"-Ereignisse ist die Eskalations-Stufe sinnvoll
     * (Original unerledigt nach X Stunden → zusätzlich an Rolle).
     */
    public function supportsEscalation(): bool {
        return in_array($this, [
            self::OpenIssueOverdue,
            self::CommunicationFollowupOverdue,
            self::DocumentExpired,
            self::IsmsCorrectiveActionOverdue,
            self::IsmsVulnerabilityOverdue,
            self::IsmsSupplierReviewOverdue,
            self::SlaBreached,
            self::AssetReturnOverdue,
            self::MaintenanceOverdue,
            // Überfällige Wirksamkeitsprüfung (Feature 065, MVP-156).
            self::ProblemEffectivenessDue,
            self::RentalReturnOverdue,
            self::AssetFinanceDeadline,
            self::ContractDeadlineDue,
            self::AssetInspectionDue,
            // Backup-Alarm eskaliert 26 h→72 h (Feature 017, MVP-056).
            self::OperationsBackupOverdue,
        ], true);
    }
}
