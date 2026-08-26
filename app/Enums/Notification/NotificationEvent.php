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
    /** Synchron: OvertimeRequestService::submit() (MVP-519) */
    case OvertimeRequested = 'overtime.requested';
    /** Synchron: OvertimeRequestService::approve()/reject() (MVP-519) */
    case OvertimeDecided = 'overtime.decided';
    /** Synchron: VacationController::store() (MVP-538) */
    case VacationRequested = 'vacation.requested';
    /** Synchron: VacationController::approve()/reject() — nur finale Entscheidung (MVP-538) */
    case VacationDecided = 'vacation.decided';
    /** Synchron: AttendancePlausibilityScanService — neuer offener Befund an die betroffene Person (MVP-538) */
    case AttendanceUnclearCase = 'attendance.unclearCase';
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
    /** Scanner: Angebotsfrist einer Ausschreibung rückt näher (Feature 108, MVP-626) */
    case TenderSubmissionDueSoon = 'tender.submissionDueSoon';
    /** Scanner: Angebotsfrist überschritten — nach ihr ist keine Abgabe mehr möglich */
    case TenderSubmissionOverdue = 'tender.submissionOverdue';
    /** Scanner: Bindefrist läuft ab — danach ist der Bieter nicht mehr gebunden */
    case TenderBindingExpiring = 'tender.bindingExpiring';
    /** Scanner: Wartungs-/Prüfplan fällig innerhalb des Vorlaufs (Feature 009) */
    case MaintenanceDueSoon = 'maintenance.dueSoon';
    /** Scanner: Wartungs-/Prüfplan mit überschrittener Fälligkeit (Feature 009) */
    case MaintenanceOverdue = 'maintenance.overdue';
    /** Synchron: neues kritisches Sicherheitsereignis (Unfall/critical) — SafetyEventService::create (Feature 013) */
    case SafetyCriticalEvent = 'safety.criticalEvent';
    /** Scanner: freigegebene Gefährdungsbeurteilung mit Wiedervorlage innerhalb des Vorlaufs bzw. überschritten (Feature 132) */
    case SafetyAssessmentReviewDue = 'safety.assessmentReviewDue';
    /** Scanner: Wiederholungsunterweisung je Teilnehmer fällig/überfällig — jüngster Nachweis je Person+Thema (Feature 132) */
    case SafetyInstructionDue = 'safety.instructionDue';
    /** Scanner: arbeitsmedizinische Vorsorge fällig/überfällig — jüngste Vorsorge je Person+Art (Feature 132) */
    case SafetyCheckupDue = 'safety.checkupDue';
    /** Scanner: Pflichtschulung fällig/überfällig — Soll-Eintrag im Vorlauf des Kurses (Feature 145) */
    case TrainingDue = 'training.due';
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

        // Betriebsereignisse (Feature 041, MVP-053–058) über den
        // OperationsAlertService — Empfänger sind Adminrollen, nie die
        // „betroffene Person".
    // Feature 070: Krisenalarm an den Krisenstab (überstimmt Ruhezeiten, D7).
    case CrisisAlert = 'crisis.alert';

    // Feature 072: Fristeneskalation überfälliger Reklamationen (MVP-255).
    case ClaimEscalation = 'claim.escalation';

    // Feature 073: überfällige Verleih-Rückgabe (MVP-264).
    case RentalReturnOverdue = 'rental.returnOverdue';
    /** Synchron: Portal-Verleihanfrage eingegangen (Feature 073, MVP-714) — an die Leitung zur Entscheidung. */
    case RentalRequested = 'rental.requested';

    // Feature 074: Leasing-/Vertragsfrist wird fällig (MVP-273/278).
    case AssetFinanceDeadline = 'assetFinance.deadline';

    // Welle D (CLM): allgemeine Vertragsfrist/-obligation wird fällig.
    case ContractDeadlineDue = 'contract.deadlineDue';

    // MVP-415: Rechnungsentwurf aus Abrechnungsplan erzeugt (nie Auto-Versand).
    case InvoiceRecurringDraft = 'invoice.recurringDraft';

    // MVP-675: Wiederkehrender Vorgang überfällig — das Original fehlt oder
    // der erzeugte Buchungsentwurf liegt ungeprüft.
    case AccountingRecurringOverdue = 'accounting.recurringOverdue';

    // MVP-686: Steuerliche Meldefrist steht an oder ist überschritten.
    case AccountingFilingDue = 'accounting.filingDue';

    // MVP-417: Führerscheinkontrolle fällig/überfällig (Halterhaftung).
    case DriverLicenseCheckDue = 'fleet.licenseCheckDue';
    /** Synchron: ComplianceFindingRecorder — neuer Lenk-/Ruhezeit-Befund an Fahrer + Disposition (Feature 144, MVP-719) */
    case DrivingTimeViolation = 'drivingTime.violation';

    // MVP-437: Öffentliche Bewerbung eingegangen (an die verantwortliche Person,
    // ohne Bewerberunterlagen im Text).
    case RecruitingApplicationReceived = 'recruiting.applicationReceived';

    // Feature 075: Prüfung fällig/überfällig (MVP-285/288).
    case AssetInspectionDue = 'assetCompliance.inspectionDue';

    // Vollaudit 2026-07 (N4): Entscheidung über die Monatsfreigabe an die
    // betroffene Person (approve/reject/reopen).
    case MonthClosureDecided = 'monthClosure.decided';

    // Vollaudit 2026-07 (H12), Feature 083 Domainverwaltung.
    case DomainExpiring = 'domain.expiring';
    case DomainTransferChanged = 'domain.transferChanged';
    case DomainSyncFailed = 'domain.syncFailed';
    case DomainHighRiskAction = 'domain.highRiskAction';

    // Vollaudit 2026-07 (M16), Feature 045 Finanzschnittstelle.
    case FinanceTransferFailed = 'finance.transferFailed';
    case FinanceBankImportFailed = 'finance.bankImportFailed';
    case FinanceReconciliationReview = 'finance.reconciliationReview';

    // Vollaudit 2026-07 (M19), Feature 048 E2: MHD-Überwachung.
    case InventoryLotExpiring = 'inventory.lotExpiring';

    // Angebots-Nachfassen (Feature 112, MVP-601).
    case QuoteFollowUpDue = 'quote.followUpDue';
    case QuoteExpiringWithoutReaction = 'quote.expiringWithoutReaction';

    // Sicherheitseinbehalte (Feature 113, MVP-602): Der Einbehalt verjährt
    // zugunsten des Kunden, wenn ihn niemand einfordert.
    case RetentionReleaseDue = 'finance.retentionReleaseDue';

    // Bürgschaftsregister (Feature 114, MVP-603). Zwei getrennte Ereignisse,
    // weil die Risiken gegenläufig sind: Eine GESTELLTE Bürgschaft, die
    // niemand zurückfordert, kostet weiter Avalprovision; eine ERHALTENE, die
    // abläuft, nimmt die Sicherheit.
    case GuaranteeExpiring = 'finance.guaranteeExpiring';
    case GuaranteeReturnDue = 'finance.guaranteeReturnDue';

    // Gewährleistungsfristen (Feature 115, MVP-604).
    case WarrantyExpiring = 'warranty.expiring';
    // Der teure Fall: Die Frist des Subunternehmers endet VOR der eigenen.
    case WarrantySubcontractorEndsFirst = 'warranty.subcontractorEndsFirst';

    // Pflichtnachweise von Subunternehmern (Feature 117, MVP-606).
    case SupplierCredentialExpiring = 'supplier.credentialExpiring';

    // Vollaudit 2026-07 (M31), Feature 069 Investitionen (MVP-209).
    case InvestmentDecisionDue = 'investment.decisionDue';
    case InvestmentDecided = 'investment.decided';

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
    case OperationsQueueDegraded = 'operations.queueDegraded';
    case OperationsMaintenanceScheduled = 'operations.maintenanceScheduled';
    case OperationsProblemReportReceived = 'operations.problemReportReceived';

    // Cloud-Dokumenteingang (Feature 080, P9; Audit 2026-08, W4.4). Bewusst
    // unter dem `operations.`-Präfix: damit greifen Default-Kanäle,
    // Admin-Empfänger und Aufgaben-Spiegelung ohne Sonderweg — der Alarm ist
    // ein Betriebsereignis, kein Vorgang einer betroffenen Person.
    case OperationsCloudIntakeReauth = 'operations.cloudIntakeReauth';
    case OperationsCloudIntakeQuarantined = 'operations.cloudIntakeQuarantined';

    // Sicherheitsereignisse (Feature 095/096): Plattform-Ebene, Versand
    // direkt an Plattform-Admins bzw. den betroffenen Nutzer — nie über
    // Org-Benachrichtigungsregeln.
    case SecurityIntegrity = 'security.integrity';
    case SecurityThreat = 'security.threat';
    case SecurityNewDevice = 'security.newDevice';
    case SecurityLockout = 'security.lockout';

    // Vollscan 2026-08-23 (B7): Legacy-Mail-/PushNotifier abgelöst — Auftrags-
    // buch-, Notdienst-, Stundenzettel- und Chat-Ereignisse laufen über den
    // zentralen Dispatcher (Org-Regeln, Präferenzen und Ruhezeiten greifen).
    /** Synchron: CommentObserver — Kommentar an einem Auftragsbuch-Eintrag. */
    case DiaryCommentCreated = 'diary.commentCreated';
    /** Synchron: DiaryEntryObserver — Eintrag steht auf „Problem". */
    case DiaryProblem = 'diary.problem';
    /** Synchron: DiaryEntryObserver — Eintrag wurde als erledigt markiert. */
    case DiaryCompleted = 'diary.completed';
    /** Synchron: AttachmentObserver — neuer Anhang an einem Eintrag. */
    case DiaryAttachmentAdded = 'diary.attachmentAdded';
    /** Synchron: EmergencyAssignmentObserver — Notdienst zugewiesen. */
    case EmergencyAssigned = 'emergency.assigned';
    /** Synchron: TimesheetObserver — Stundenzettel vom Kunden signiert. */
    case TimesheetSigned = 'timesheet.signed';
    /** Synchron: ChatNotificationService — Direktnachricht bzw. @-Erwähnung. */
    case ChatMessage = 'chat.message';
    /** Scanner: chat:send-reminders — fällige Chat-Erinnerung. */
    case ChatReminder = 'chat.reminder';
    /** Scanner: Wettervorhersage reißt eine Org-Schwelle für einen disponierten Einsatz (Feature 062, MVP-716) */
    case WeatherWarning = 'weather.warning';

    public function label(): string {
        return (string) __('enums.notification.event.' . $this->value);
    }

    /**
     * Default-Kanäle, solange keine Org-Regel existiert.
     *
     * @return list<string>
     */
    public function defaultChannels(): array {
        return match ($this) {
            // Migrierte Push-Ereignisse (B7): Mail bewusst kein Default — der
            // alte MAIL_NOTIFICATIONS_ENABLED-Schalter stand default aus;
            // Orgs schalten den Mail-Kanal je Regel zu.
            self::DiaryCommentCreated,
            self::DiaryProblem,
            self::DiaryAttachmentAdded,
            self::EmergencyAssigned,
            self::TimesheetSigned => [NotificationChannel::InApp->value, NotificationChannel::Push->value],
            self::DiaryCompleted => [NotificationChannel::InApp->value],
            // Chat hat eigene Ungelesen-Zähler — kein In-App-Duplikat.
            self::ChatMessage,
            self::ChatReminder => [NotificationChannel::Push->value],
            default => [NotificationChannel::InApp->value, NotificationChannel::Mail->value],
        };
    }

    /**
     * Geht die Benachrichtigung per Default an die betroffene Person?
     * Bei Workflow-Anträgen (requested/submitted) ist die betroffene Person
     * der Antragsteller selbst — Empfänger sind hier die Entscheider (Rolle).
     */
    public function defaultNotifyAffected(): bool {
        // Betriebs-/Sicherheitsereignisse haben keine „betroffene Person" im
        // Sinne der Org-Regeln (Security-Versand läuft direkt, s. Cases).
        if (str_starts_with($this->value, 'operations.') || str_starts_with($this->value, 'security.')) {
            return false;
        }

        return ! in_array($this, [self::TimeCorrectionRequested, self::OvertimeRequested, self::VacationRequested, self::MonthClosureSubmitted, self::IsmsCertificateExpiring, self::IsmsIncidentCritical, self::SafetyCriticalEvent, self::SafetyAssessmentReviewDue, self::ShiftExchangeRequested, self::CustomerQueryRaised, self::RentalRequested, self::ShipmentDeliveryProblem, self::SlaQuotaWarning,
            // Domain-/Finanz-/Fristereignisse betreffen keine Einzelperson (Vollaudit 2026-07, W3.2).
            self::DomainExpiring, self::DomainTransferChanged, self::DomainSyncFailed, self::DomainHighRiskAction,
            self::FinanceTransferFailed, self::FinanceBankImportFailed, self::FinanceReconciliationReview, self::RetentionReleaseDue,
            self::GuaranteeExpiring, self::GuaranteeReturnDue, self::SupplierCredentialExpiring,
            self::InvestmentDecisionDue, self::InventoryLotExpiring], true);
    }

    /**
     * Default-Empfängerrollen (zusätzlich bzw. statt der betroffenen Person).
     *
     * @return list<string>
     */
    public function defaultRecipientRoles(): array {
        // Betriebsereignisse gehen per Default an die Admin-Rolle der Org
        // (MVP-058 führt sie zusätzlich als Aufgabe im Admin-Aufgabencenter).
        if (str_starts_with($this->value, 'operations.')) {
            return [UserRole::Admin->value];
        }

        return match ($this) {
            self::TimeCorrectionRequested,
            self::OvertimeRequested,
            self::VacationRequested,
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
            // Vergabefristen entscheiden über Teilnahme oder Ausschluss - sie
            // gehen an die Verantwortlichen der Akte, ersatzweise an die
            // Teamleitung.
            self::TenderSubmissionDueSoon,
            self::TenderSubmissionOverdue,
            self::TenderBindingExpiring => [UserRole::Teamleitung->value],
            // Wartungs-/Prüffälligkeit (MVP-336): primär der Asset-
            // Verantwortliche (notify_affected), Teamleitung als Fallback;
            // Überfälligkeit eskaliert zusätzlich (MVP-331).
            self::MaintenanceDueSoon,
            self::MaintenanceOverdue => [UserRole::Teamleitung->value],
            // Kritisches Sicherheitsereignis: betrifft keine einzelne Person —
            // synchron an die Leitungs-/Admin-Rollen der Organisation.
            self::SafetyCriticalEvent => [UserRole::Teamleitung->value, UserRole::Admin->value],
            // Arbeitsschutz-Fristen (Feature 132): GBU-Wiedervorlage betrifft keine
            // Einzelperson; Unterweisung/Vorsorge primär die Person (notify_affected),
            // die Teamleitung führt das Register und ist Fallback.
            self::SafetyAssessmentReviewDue,
            self::SafetyInstructionDue,
            self::SafetyCheckupDue,
            // Feature 145: Eskalation der Schulungspflicht an die Teamleitung.
            self::TrainingDue => [UserRole::Teamleitung->value],
            // Ablaufende Qualifikation/Unterweisung: primär die betroffene
            // Person (notify_affected), Default-Fallback die Teamleitung.
            self::QualificationExpiring => [UserRole::Teamleitung->value],
            // Schichttausch-Antrag (Feature 007): Teamleitung (Freigabe);
            // der Ziel-Kollege wird separat im Service adressiert.
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
            // Portal-Verleihanfrage (MVP-714): Entscheidung ist Leitungsaufgabe.
            self::RentalRequested => [UserRole::Teamleitung->value],
            // Leasingfristen (Feature 074): Vertrags-/Fristensteuerung ist
            // Leitungsaufgabe; der Verantwortliche der Akte via Service.
            self::AssetFinanceDeadline => [UserRole::Teamleitung->value],
            // Allgemeine Vertragsfristen (Welle D, CLM): Vertragssteuerung ist
            // Leitungsaufgabe; der Verantwortliche der Obligation via Service.
            self::ContractDeadlineDue => [UserRole::Teamleitung->value],
            // Wiederkehrende Rechnungsentwürfe (MVP-415): kaufmännische Prüfung.
            self::InvoiceRecurringDraft => [UserRole::Buchhaltung->value],
            // Belegerwartung/Buchungsvorlage (MVP-675): dieselbe Zielgruppe.
            self::AccountingRecurringOverdue, self::AccountingFilingDue => [UserRole::Buchhaltung->value],
            // Führerscheinkontrolle (MVP-417): Fahrer selbst (notify_affected)
            // plus Teamleitung (Fuhrparkverantwortung).
            self::DriverLicenseCheckDue => [UserRole::Teamleitung->value],
            // Lenk-/Ruhezeit-Befund (Feature 144): Fahrer selbst (notify_affected)
            // plus Disposition/Teamleitung (Fuhrpark- und Tourenverantwortung).
            self::DrivingTimeViolation => [UserRole::Teamleitung->value],
            // Prüffälligkeit (Feature 075): betrifft keine Einzelperson —
            // an die Teamleitung (Prüfmittelverantwortung).
            self::AssetInspectionDue => [UserRole::Teamleitung->value],
            // Fällige Wirksamkeitsprüfung (Feature 065, MVP-156): primär der
            // Problem-Owner (notify_affected), Teamleitung als Fallback.
            self::ProblemEffectivenessDue => [UserRole::Teamleitung->value],
            // Domainverwaltung (H12): Betrieb/Registrar-Themen sind Admin-Sache.
            self::DomainExpiring,
            self::DomainTransferChanged,
            self::DomainSyncFailed,
            self::DomainHighRiskAction => [UserRole::Admin->value],
            // Freigabe des Sicherheitseinbehalts (MVP-602): kaufmännische Sache.
            self::RetentionReleaseDue,
            // Bürgschaften (MVP-603): Sicherheiten sind kaufmännische Steuerung.
            self::GuaranteeExpiring,
            self::GuaranteeReturnDue => [UserRole::Buchhaltung->value],
            // Gewährleistung (MVP-604): primär der Verantwortliche der Frist
            // (notify_affected), die Teamleitung als Fallback — eine
            // ablaufende Frist darf nicht an einem Urlaub scheitern.
            self::WarrantyExpiring,
            self::WarrantySubcontractorEndsFirst => [UserRole::Teamleitung->value],
            // Pflichtnachweise (MVP-606): Einkauf/Leitung — der Nachweis muss
            // beim Lieferanten angefordert werden, das ist keine Buchhaltung.
            self::SupplierCredentialExpiring => [UserRole::Teamleitung->value],
            // Finanzschnittstelle (M16): kaufmännische Klärung.
            self::FinanceTransferFailed,
            self::FinanceBankImportFailed,
            self::FinanceReconciliationReview => [UserRole::Buchhaltung->value],
            // Investitionsfristen (M31): Entscheidung ist Leitungs-/Kaufmannssache;
            // die Entscheidung selbst geht an den Antragsteller (notify_affected).
            self::InvestmentDecisionDue => [UserRole::Teamleitung->value, UserRole::Buchhaltung->value],
            // MHD-Überwachung (M19): Lagerverantwortung ist Leitungsaufgabe.
            self::InventoryLotExpiring => [UserRole::Teamleitung->value],
            // Angebots-Nachfassen (MVP-601): primär der zugewiesene Zuständige
            // (notify_affected), die Teamleitung als Fallback — ein Angebot,
            // dessen Zuständiger im Urlaub ist, darf nicht auslaufen.
            self::QuoteFollowUpDue,
            self::QuoteExpiringWithoutReaction => [UserRole::Teamleitung->value],
            // Wetterwarnung: zugewiesene Person + Disposition (Teamleitung).
            self::WeatherWarning => [UserRole::Teamleitung->value],
            // Problem-Eintrag (B7): wie der Legacy-Push an Admin + Callcenter;
            // der Eintrags-Besitzer läuft zusätzlich über notify_affected.
            self::DiaryProblem => [UserRole::Admin->value, UserRole::Callcenter->value],
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
            self::OvertimeRequested,
            self::OvertimeDecided => 'more_time',
            self::VacationRequested,
            self::VacationDecided => 'beach_access',
            self::AttendanceUnclearCase => 'live_help',
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
            self::TenderSubmissionDueSoon => 'schedule',
            self::TenderSubmissionOverdue => 'event_busy',
            self::TenderBindingExpiring => 'gavel',
            self::MaintenanceDueSoon,
            self::MaintenanceOverdue => 'handyman',
            self::SafetyCriticalEvent => 'e911_emergency',
            self::SafetyAssessmentReviewDue => 'checklist',
            self::SafetyInstructionDue => 'school',
            self::SafetyCheckupDue => 'medical_services',
            self::TrainingDue => 'school',
            self::CrisisAlert => 'emergency_home',
            self::ClaimEscalation => 'assignment_late',
            self::RentalReturnOverdue => 'forklift',
            self::RentalRequested => 'forklift',
            self::AssetFinanceDeadline => 'request_quote',
            self::ContractDeadlineDue => 'contract',
            self::InvoiceRecurringDraft => 'receipt_long',
            self::AccountingRecurringOverdue => 'event_repeat',
            self::AccountingFilingDue => 'event_available',
            self::DriverLicenseCheckDue => 'badge',
            self::DrivingTimeViolation => 'local_shipping',
            self::RecruitingApplicationReceived => 'work',
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
            self::OperationsQueueDegraded => 'pending_actions',
            self::OperationsMaintenanceScheduled => 'engineering',
            self::OperationsProblemReportReceived => 'flag',
            self::OperationsCloudIntakeReauth => 'cloud_off',
            self::OperationsCloudIntakeQuarantined => 'block',
            self::MonthClosureDecided => 'event_available',
            self::DomainExpiring => 'timer',
            self::DomainTransferChanged => 'swap_horiz',
            self::DomainSyncFailed => 'sync_problem',
            self::DomainHighRiskAction => 'gpp_maybe',
            self::FinanceTransferFailed => 'money_off',
            self::FinanceBankImportFailed => 'account_balance',
            self::FinanceReconciliationReview => 'rule',
            self::InvestmentDecisionDue => 'pending_actions',
            self::InvestmentDecided => 'task_alt',
            self::InventoryLotExpiring => 'hourglass_bottom',
            self::RetentionReleaseDue => 'savings',
            self::GuaranteeExpiring => 'gpp_maybe',
            self::GuaranteeReturnDue => 'assignment_return',
            self::WarrantyExpiring => 'shield_with_heart',
            self::WarrantySubcontractorEndsFirst => 'crisis_alert',
            self::SupplierCredentialExpiring => 'verified_user',
            self::QuoteFollowUpDue => 'phone_forwarded',
            self::QuoteExpiringWithoutReaction => 'running_with_errors',
            self::WeatherWarning => 'thunderstorm',
            self::SecurityIntegrity => 'verified_user',
            self::SecurityThreat => 'gpp_bad',
            self::SecurityNewDevice => 'devices',
            self::SecurityLockout => 'lock_person',
            self::DiaryCommentCreated => 'comment',
            self::DiaryProblem => 'report',
            self::DiaryCompleted => 'task_alt',
            self::DiaryAttachmentAdded => 'attach_file',
            self::EmergencyAssigned => 'e911_emergency',
            self::TimesheetSigned => 'draw',
            self::ChatMessage => 'chat',
            self::ChatReminder => 'alarm',
        };
    }

    /**
     * Darf dieses Ereignis über den SMS-Kanal gehen (Feature 147, MVP-730)?
     *
     * SMS ist der einzige Kanal ohne Datenverbindung und damit der
     * Alarmierungsweg der Krisen-/Notfalllage (Feature 070) — er kostet aber
     * Geld je Nachricht und trägt die Rufnummer zu einem Gateway. Deshalb ist
     * die Liste bewusst KURZ und hier hart verankert: eine Organisation kann
     * den Kanal in ihrer Regel nur an diesen Ereignissen überhaupt wählen,
     * nicht an den restlichen Fristen-/Workflow-Meldungen.
     */
    public function supportsSms(): bool {
        return in_array($this, [
            // Krisenstab-Alarmierung (Feature 070, D7) — der Anlass des Kanals.
            self::CrisisAlert,
            // Notfalleinsatz an eine konkrete Person (Feature 007/070).
            self::EmergencyAssigned,
            // Unfall/kritisches Sicherheitsereignis (Feature 013).
            self::SafetyCriticalEvent,
            // Kritischer Sicherheitsvorfall im ISMS (Feature 044).
            self::IsmsIncidentCritical,
            // Aktive Bedrohung/Angriff auf die Installation (Feature 095/096).
            self::SecurityThreat,
            // Wetterwarnung für einen disponierten Einsatz (Feature 062) —
            // optional: draußen ohne Datenverbindung ist SMS oft der einzige Weg.
            self::WeatherWarning,
        ], true);
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
