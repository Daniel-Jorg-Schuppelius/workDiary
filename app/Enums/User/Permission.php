<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : Permission.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\User;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Single Source of Truth für alle feingranularen Permissions im neuen
 * System. Werte (`<resource>.<action>`) sind identisch mit den Einträgen
 * in `permissions.name`. Übersetzungen liegen unter `lang/{locale}/access.php`
 * im Schlüsselraum `access.permission.<value>`.
 *
 * Granularität: pro Ressource CRUD (view/viewAny/create/update/delete) plus
 * fachliche Spezialaktionen (approve, export, sign, ...). Die Gruppierung
 * über {@see PermissionGroup} steuert ausschließlich die UI-Anzeige.
 */
enum Permission: string implements HasLabel {
    use HasOptions;

        // ── Zugriff / Verwaltung ───────────────────────────────────────────
    case AccessManage = 'access.manage';
    case AccessAssignRoles = 'access.roles.assign';
    case AccessAssignGroups = 'access.groups.assign';
    case AccessAuditView = 'access.audit.view';

        // ── Organisation / Branding ────────────────────────────────────────
    case OrganizationView = 'organization.view';
    case OrganizationUpdate = 'organization.update';
    case OrganizationBilling = 'organization.billing';
    case BrandingUpdate = 'branding.update';
        // ── PDF-Dokumentdesign / Firmenbogen (Feature 076) ──────────
    case DocumentDesignManage = 'documentDesign.manage';
    case DocumentDesignAssign = 'documentDesign.assign';
        // ── orgaMAX-Faktura-Aktionen (Feature 077, MVP-310) ─────────
        // ── Kassenbuch (Phase 38, MVP-414) ──────────────────────────
    case CashView = 'finance.cash.view';
    case CashManage = 'finance.cash.manage';
    case OrgamaxInvoiceConvert = 'finance.orgamax.convert';
    case OrgamaxInvoiceLock = 'finance.orgamax.lock';
    case OrgamaxInvoiceSend = 'finance.orgamax.send';
    case OrgamaxPaymentRecord = 'finance.orgamax.payment';
    case OrgOnboardingView = 'org.onboarding.view';
    case OrgOnboardingSkipStep = 'org.onboarding.skipStep';
    case OrgOnboardingDismissWidget = 'org.onboarding.dismissWidget';
    case NumberFormatManage = 'organization.numberFormat.manage';
        // ── Plattform-Diagnose (MVP-044) ────────────────────────────
    case PlatformDiagnosticsView = 'platform.diagnostics.view';
    case PlatformDiagnosticsRunCheck = 'platform.diagnostics.runCheck';
        // ── Scheduler-Steuerung (Feature 067, MVP-176) ──────────────
    case PlatformSchedulerManage = 'platform.scheduler.manage';
        // ── Einstellungs-Registry (Feature 067, MVP-174) ────────────
    case PlatformSettingsManage = 'platform.settings.manage';
        // ── Admin-Aufgabencenter (Feature 041, MVP-058) ─────────────
    case PlatformOperationsView = 'platform.operations.view';
    case PlatformOperationsManage = 'platform.operations.manage';
        // ── Fehlermeldesystem (Feature 041, MVP-053) ────────────────
    case ProblemReportManage = 'platform.problemReports.manage';
        // ── Betriebsmetriken (Feature 036) ──────────────────────────
    case MetricsView = 'metrics.view';
        // ── Admin-Sicherheitsübersicht (Feature 016) ────────────────
    case SecurityView = 'security.view';
        // ── Sitzungsverwaltung / Fernabmeldung (Feature 085) ────────
    case SecuritySessionsView = 'security.sessions.view';
    case SecuritySessionsRevoke = 'security.sessions.revoke';
        // ── Backup & Restore-Status (Feature 017) ───────────────────
    case BackupView = 'backup.view';
    case BackupRestoreTestLog = 'backup.restoreTest.log';
        // ── Plattform-Supportbericht (MVP-045) ──────────────────────
    case PlatformSupportExport = 'platform.support.export';
    case PlatformSupportExportWithSamples = 'platform.support.exportWithSamples';
        // ── Plattform-Lizenz (MVP-047) ──────────────────────────────
    case PlatformLicenseView = 'platform.license.view';
    case PlatformLicenseInstall = 'platform.license.install';
    case PlatformFeatureFlagOverride = 'platform.featureFlag.override';
        // ── Funktionsumfang der Organisation (Feature 081, MVP-373) ─
    case OrganizationScopeManage = 'organization.scope.manage';
        // ── Demo-Mandant (MVP-050) ──────────────────────────────────
    case PlatformDemoCreate = 'platform.demo.create';
    case PlatformDemoReset = 'platform.demo.reset';
    case OrgDemoSeed = 'org.demo.seed';
        // ── Datenschutzseite (MVP-005) ──────────────────────────────
    case PrivacyView = 'privacy.view';
    case PrivacySessionsView = 'privacy.sessions.view';
    case PrivacySessionsRevoke = 'privacy.sessions.revoke';
    case PrivacyTokensView = 'privacy.tokens.view';
    case PrivacyTokensRevoke = 'privacy.tokens.revoke';
    case PrivacyIntegrationsView = 'privacy.integrations.view';
    case PrivacyExportsView = 'privacy.exports.view';
    case PrivacySupportView = 'privacy.support.view';
    case PrivacyReportExport = 'privacy.report.export';

        // Temporäre Supportfreigabe (Rang 64): Kundenadmin erteilt/widerruft
        // zeitlich begrenzte Support-Zugriffe inkl. Impersonations-Erlaubnis.
    case SupportGrantManage = 'support.grant.manage';

        // ── Agiles Projektmanagement (Feature 064) ────────────────────────────
    case AgileView = 'agile.view';
    case AgileBoardManage = 'agile.board.manage';
    case AgileBacklogPrioritize = 'agile.backlog.prioritize';
    case AgileSprintManage = 'agile.sprint.manage';
    case AgileWorkItemMove = 'agile.workitem.move';
    case AgileWorkflowOverride = 'agile.workflow.override';
    case AgileReportView = 'agile.report.view';

        // ── Mitglieder (User-Stamm der Org) ────────────────────────────────
    case UserViewAny = 'user.viewAny';
    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';
    case UserImpersonate = 'user.impersonate';
    case UserResetPassword = 'user.reset-password';
    case UserManageRates = 'user.rates.manage';
    case UserFlexManage = 'user.flex.manage';
    case UserPayrollManage = 'user.payroll.manage';
    case UserImport = 'user.import';

        // ── Arbeits-Teams ──────────────────────────────────────────────────
    case TeamViewAny = 'team.viewAny';
    case TeamView = 'team.view';
    case TeamCreate = 'team.create';
    case TeamUpdate = 'team.update';
    case TeamDelete = 'team.delete';
    case TeamManageMembers = 'team.manageMembers';

        // ── Kunden ─────────────────────────────────────────────────────────
    case CustomerViewAny = 'customer.viewAny';
    case CustomerView = 'customer.view';
    case CustomerCreate = 'customer.create';
    case CustomerUpdate = 'customer.update';
    case CustomerDelete = 'customer.delete';
    case CustomerExport = 'customer.export';
    case CustomerImport = 'customer.import';
    case CustomerLexofficeSync = 'customer.lexoffice.sync';

        // ── Fremdkunden (Endkunden) ────────────────────────────────────────
    case ForeignCustomerViewAny = 'foreignCustomer.viewAny';
    case ForeignCustomerView = 'foreignCustomer.view';
    case ForeignCustomerCreate = 'foreignCustomer.create';
    case ForeignCustomerUpdate = 'foreignCustomer.update';
    case ForeignCustomerDelete = 'foreignCustomer.delete';
    case ForeignCustomerPromote = 'foreignCustomer.promote';

        // ── Lieferanten ────────────────────────────────────────────────────
    case SupplierViewAny = 'supplier.viewAny';
    case SupplierView = 'supplier.view';
    case SupplierCreate = 'supplier.create';
    case SupplierUpdate = 'supplier.update';
    case SupplierDelete = 'supplier.delete';
    case SupplierExport = 'supplier.export';
    case SupplierImport = 'supplier.import';
    case SupplierLexofficeSync = 'supplier.lexoffice.sync';

        // ── Produkte & Leistungen (Lexoffice-Artikel) ──────────────────────
    case ArticleViewAny = 'article.viewAny';
    case ArticleLexofficeSync = 'article.lexoffice.sync';

        // ── Belege (Lexoffice-Vouchers) ────────────────────────────────────
    case VoucherViewAny = 'voucher.viewAny';
    case VoucherLexofficeSync = 'voucher.lexoffice.sync';

        // ── Projekte / Aufgaben / Meilensteine ─────────────────────────────
    case ProjectViewAny = 'project.viewAny';
    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case ProjectArchive = 'project.archive';
    case ProjectManageBilling = 'project.billing.manage';
    case ProjectImport = 'project.import';
    case TaskManage = 'task.manage';
    case MilestoneManage = 'milestone.manage';

        // ── Zeiteinträge ───────────────────────────────────────────────────
    case TimeEntryViewAny = 'timeEntry.viewAny';
    case TimeEntryViewOwn = 'timeEntry.viewOwn';
    case TimeEntryCreate = 'timeEntry.create';
    case TimeEntryUpdate = 'timeEntry.update';
    case TimeEntryDelete = 'timeEntry.delete';
    case TimeEntryCreateForOthers = 'timeEntry.create-for-others';
    case TimeEntryApprove = 'timeEntry.approve';

        // ── Stundenzettel ──────────────────────────────────────────────────
    case TimesheetViewAny = 'timesheet.viewAny';
    case TimesheetCreate = 'timesheet.create';
    case TimesheetUpdate = 'timesheet.update';
    case TimesheetDelete = 'timesheet.delete';
    case TimesheetSign = 'timesheet.sign';
    case TimesheetLock = 'timesheet.lock';
    case TimesheetUnlock = 'timesheet.unlock';
    case TimesheetExport = 'timesheet.export';

        // ── Monatsfreigabe (MVP-016) ───────────────────────────────────────
    case MonthViewOwn = 'month.view.own';
    case MonthViewTeam = 'month.view.team';
    case MonthViewOrganization = 'month.view.organization';
    case MonthSubmitOwn = 'month.submit.own';
    case MonthApprove = 'month.approve';
    case MonthReject = 'month.reject';
    case MonthReopen = 'month.reopen';
    case MonthLock = 'month.lock';

        // ── Zeit-Korrekturanträge (MVP-017) ────────────────────────────────
    case CorrectionCreateOwn = 'correction.create.own';
    case CorrectionCreateForOthers = 'correction.create.others';
    case CorrectionSubmitOwn = 'correction.submit.own';
    case CorrectionWithdrawOwn = 'correction.withdraw.own';
    case CorrectionViewTeam = 'correction.view.team';
    case CorrectionViewOrganization = 'correction.view.organization';
    case CorrectionApprove = 'correction.approve';
    case CorrectionReject = 'correction.reject';
    case CorrectionApplySystem = 'correction.apply.system';

        // ── Tagesabschluss (MVP-015) ───────────────────────────────────────
    case DayCloseViewOwn = 'dayClose.view.own';
    case DayCloseViewTeam = 'dayClose.view.team';
    case DayCloseViewOrganization = 'dayClose.view.organization';
    case DayCloseCloseOwn = 'dayClose.close.own';
    case DayCloseRequestCorrectionOwn = 'dayClose.requestCorrection.own';
    case DayCloseApproveCorrection = 'dayClose.approveCorrection';
    case DayCloseReopen = 'dayClose.reopen';

        // ── Zeit-Export / Lohnübergabe (MVP-019) ───────────────────────────
    case ExportTimeCreate = 'export.time.create';
    case ExportTimeDeliver = 'export.time.deliver';
    case ExportTimeDelete = 'export.time.delete';

        // ── Zuschlagsregeln (Feature 005) ──────────────────────────────────
    case SurchargeRuleViewAny = 'surchargeRule.viewAny';
    case SurchargeRuleManage = 'surchargeRule.manage';

        // ── Kostenstellen-Regeln für den Zeitexport (Rang 35) ──────────────
    case CostCenterRuleViewAny = 'costCenterRule.viewAny';
    case CostCenterRuleManage = 'costCenterRule.manage';

        // ── Lohnarten-Mapping + Export-Lieferung Zeitexport (A21 · MVP-019) ─
    case WageTypeMappingViewAny = 'wageTypeMapping.viewAny';
    case WageTypeMappingManage = 'wageTypeMapping.manage';

        // ── Plan/Ist-Anwesenheit Team-/Org-Sicht (MVP-018, Rang 38) ────────
    case ReportPresenceTeam = 'report.presence.team';
    case ReportPresenceOrganization = 'report.presence.organization';

        // ── Finanzschnittstelle (Feature 045) ──────────────────────────────
    case FinanceViewAny = 'finance.viewAny';
    case FinanceConfig = 'finance.config';
    case FinanceTransferTime = 'finance.transfer.time';
    case FinanceTransferMaterial = 'finance.transfer.material';
    case FinancePaymentImport = 'finance.payment.import';
    case FinancePaymentReconcile = 'finance.payment.reconcile';
    case FinanceBookingExport = 'finance.booking.export';
    case FinanceGobdExport = 'finance.gobd.export';

        // ── Rechnungen ─────────────────────────────────────────────────────
    case InvoiceViewAny = 'invoice.viewAny';
    case InvoiceView = 'invoice.view';
    case InvoiceCreate = 'invoice.create';
    case InvoiceUpdate = 'invoice.update';
    case InvoiceDelete = 'invoice.delete';
    case InvoiceIssue = 'invoice.issue';
    case InvoicePay = 'invoice.pay';
    case InvoiceExport = 'invoice.export';

        // ── Diary / Tagebuch ───────────────────────────────────────────────
    case DiaryViewAny = 'diary.viewAny';
    case DiaryViewOwn = 'diary.viewOwn';
    case DiaryCreate = 'diary.create';
    case DiaryUpdate = 'diary.update';
    case DiaryDelete = 'diary.delete';
    case DiaryCreateForOthers = 'diary.create-for-others';
    case DiaryExport = 'diary.export';
    case OrderAccept = 'order.accept';
    case OrderWork = 'order.work';
    case OrderComplete = 'order.complete';
    case OrderHandover = 'order.handover';
    case OrderMarkInvoiced = 'order.markInvoiced';
    case OrderCancel = 'order.cancel';

        // ── Disposition / Einsatzplanung (Feature 028) ─────────────────────
    case DispatchViewAny = 'dispatch.viewAny';
    case DispatchManage = 'dispatch.manage';

        // ── Dienstpläne / Schichten ────────────────────────────────────────
    case DutyPlanViewAny = 'dutyPlan.viewAny';
    case DutyPlanCreate = 'dutyPlan.create';
    case DutyPlanUpdate = 'dutyPlan.update';
    case DutyPlanDelete = 'dutyPlan.delete';
    case DutyPlanPublish = 'dutyPlan.publish';
    case ShiftManage = 'shift.manage';
    case CoverageRequirementManage = 'coverage-requirement.manage';
    case OnCallShiftManage = 'on-call-shift.manage';
    case EmergencyAssignmentManage = 'emergency-assignment.manage';
    case ShiftTypeManage = 'shift-type.manage';
    case ScheduledShiftManage = 'scheduled-shift.manage';
        // Dienstplan-Intelligenz (Feature 007)
    case ShiftExchangeRequest = 'shift.exchange';
    case ShiftExchangeApprove = 'shift.exchange.approve';
    case AvailabilityManageOwn = 'availability.manage.own';
    case StaffingSuggest = 'staffing.suggest';

        // ── Abwesenheiten ──────────────────────────────────────────────────
    case VacationViewAny = 'vacation.viewAny';
    case VacationRequest = 'vacation.request';
    case VacationApprove = 'vacation.approve';
    case VacationCancel = 'vacation.cancel';
        // Urlaubskonto (MVP-413): Jahresansprüche + Übertrag pflegen
    case VacationEntitlementsManage = 'vacation.entitlements.manage';
    case SickLeaveViewAny = 'sick-leave.viewAny';
    case SickLeaveManage = 'sick-leave.manage';

        // ── Touren / Fuhrpark ──────────────────────────────────────────────
    case TourViewAny = 'tour.viewAny';
    case TourManage = 'tour.manage';
    case VehicleViewAny = 'vehicle.viewAny';
    case VehicleManage = 'vehicle.manage';
    case VehicleReserve = 'vehicle.reserve';
    case VehicleImport = 'vehicle.import';
    case TravelLogViewAny = 'travel-log.viewAny';
    case TravelLogManage = 'travel-log.manage';
    case EnergyLogManage = 'energy-log.manage';

        // ── Personenbeförderung (MVP-456, Branchenprofil Taxi/Mietwagen) ──
    case PassengerViewAny = 'passenger.viewAny';
    case PassengerView = 'passenger.view';
    case PassengerManage = 'passenger.manage';
    case PassengerSettle = 'passenger.settle';

        // ── Stammdaten / Listen ────────────────────────────────────────────
    case MaterialManage = 'material.manage';
    case MaterialImport = 'material.import';
    case RemoteSessionImport = 'remote-session.import';
    case ActivityCategoryManage = 'activity-category.manage';
    case TagManage = 'tag.manage';
    case QualificationManage = 'qualification.manage';
    case EntryTypeManage = 'entry-type.manage';
    case HolidayManage = 'holiday.manage';

        // ── Reporting / Audit / Sonstiges ──────────────────────────────────
    case ReportView = 'report.view';
    case ReportExport = 'report.export';
        // Feature 002: Zielwerte/Benchmarks je Kennzahl pflegen (GF/Admin).
    case ReportTargetManage = 'report.target.manage';
    case ImportViewReports = 'import.viewReports';
        // ── Cloud-Dokumenteingang (Feature 080, MVP-351) ─────────────────
    case CloudIntakeConnectionManage = 'cloudIntake.connection.manage';
    case CloudIntakeRouteManage = 'cloudIntake.route.manage';
    case CloudIntakeRunPreview = 'cloudIntake.run.preview';
    case AuditLogView = 'audit-log.view';
    case AttendanceViewAny = 'attendance.viewAny';
    case AttendanceManage = 'attendance.manage';
        // MVP-438: Zeiterfassungs-Import (CSV/XLSX/iCal). Stempelungen streng
        // (Admin/HR), Projektzeiten breiter vergebbar.
    case AttendanceImport = 'attendance.import';
    case ProjectTimeImport = 'project-time.import';
        // ArbZG-Compliance-Auswertung auf Ist-Arbeitszeit (Feature 006).
    case ComplianceViewAny = 'compliance.viewAny';
    case WorkScheduleManage = 'work-schedule.manage';
    case FlexBalanceView = 'flex.view';
    case FlexBalanceManage = 'flex.manage';

        // ── Offene Punkte (Snagging / Restpunkte) ──────────────────────────
    case OpenIssueViewAny = 'openIssue.viewAny';
    case OpenIssueView = 'openIssue.view';
    case OpenIssueCreate = 'openIssue.create';
    case OpenIssueUpdate = 'openIssue.update';
    case OpenIssueAssign = 'openIssue.assign';
    case OpenIssuePublishToCustomer = 'openIssue.publishToCustomer';
    case OpenIssueDelete = 'openIssue.delete';

        // ── Arbeitsschutz / Sicherheitsereignisse (Feature 013) ────────────
    case SafetyViewAny = 'safety.viewAny';
    case SafetyReport = 'safety.report';
    case SafetyManage = 'safety.manage';

        // ── Benachrichtigungsregeln (MVP-018) ──────────────────────────────
    case NotificationRuleViewAny = 'notificationRule.viewAny';
    case NotificationRuleUpdate = 'notificationRule.update';

        // ── Kommunikationsnotizen (MVP-012) ────────────────────────────────
    case CommunicationViewAny = 'communication.viewAny';
    case CommunicationView = 'communication.view';
    case CommunicationCreate = 'communication.create';
    case CommunicationUpdate = 'communication.update';
    case CommunicationDelete = 'communication.delete';
    case CommunicationPublishToCustomer = 'communication.publishToCustomer';
    case CommunicationConfidentialManage = 'communication.confidential.manage';

        // ── Dokumentenmanagement (MVP-031) ─────────────────────────────────
    case DocumentViewAny = 'document.viewAny';
    case DocumentView = 'document.view';
    case DocumentCreate = 'document.create';
    case DocumentUpdate = 'document.update';
    case DocumentDelete = 'document.delete';
    case DocumentArchive = 'document.archive';
        // Vertrauliche Dokumente Dritter sehen/verwalten (Vollaudit 2026-07, N10).
    case DocumentConfidentialManage = 'document.confidential.manage';

        // ── Wissensbasis & Problemhistorie (Feature 011) ───────────────────
    case KnowledgeViewAny = 'knowledge.viewAny';
    case KnowledgeView = 'knowledge.view';
    case KnowledgeCreate = 'knowledge.create';
    case KnowledgeUpdate = 'knowledge.update';
    case KnowledgePublish = 'knowledge.publish';
    case KnowledgeDelete = 'knowledge.delete';

        // ── Ideenlandkarten (Feature 054, MVP-104) ─────────────────────────
        // Inhaltszugriff läuft über Eigentum + Freigaben (IdeaMapPolicy); diese
        // Rechte steuern nur Menü/Anlage bzw. Admin-Metadatenpflege.
    case IdeasViewAny = 'ideas.viewAny';
    case IdeasCreate = 'ideas.create';
    case IdeasManageLifecycle = 'ideas.manageLifecycle';

        // ── ISMS / ISO-27001-Auditbereitschaft (Feature 044) ───────────────
    case IsmsViewAny = 'isms.viewAny';
    case IsmsView = 'isms.view';
    case IsmsManage = 'isms.manage';

        // ── Vorlagen- & Formularsystem (Feature 032) ───────────────────────
    case FormTemplateViewAny = 'formTemplate.viewAny';
    case FormTemplateManage = 'formTemplate.manage';
    case FormSubmissionViewAny = 'formSubmission.viewAny';
    case FormSubmissionView = 'formSubmission.view';
    case FormSubmissionCreate = 'formSubmission.create';

        // ── Protokolle (MVP-020) ───────────────────────────────────────────
    case ProtocolView = 'protocol.view';
    case ProtocolViewAny = 'protocol.viewAny';
    case ProtocolCreate = 'protocol.create';
    case ProtocolEditDraft = 'protocol.editDraft';
    case ProtocolRequestReview = 'protocol.requestReview';
    case ProtocolSignInternal = 'protocol.signInternal';
    case ProtocolSignCustomer = 'protocol.signCustomer';
    case ProtocolArchive = 'protocol.archive';
    case ProtocolSupersede = 'protocol.supersede';
    case ProtocolDelete = 'protocol.delete';
    case ProtocolSignatureRequest = 'protocol.signatureRequest';
    case ProtocolPdfDownload = 'protocol.pdfDownload';
    case ProtocolItemPhotoAdd = 'protocol.item.photo.add';
    case ProtocolItemPhotoRemove = 'protocol.item.photo.remove';
    case ProtocolItemPhotoViewGeo = 'protocol.item.photo.viewGeo';

        // ── Externe Beteiligte (Feature 033) ─────────────────────
    case ExternalParticipantManage = 'externalParticipant.manage';

        // ── Prozedurvorlagen (MVP-025) ──────────────────────────
    case ProcedureTemplateView = 'procedure.template.view';
    case ProcedureTemplateCreate = 'procedure.template.create';
    case ProcedureTemplateUpdate = 'procedure.template.update';
    case ProcedureTemplatePublish = 'procedure.template.publish';

        // ── Prozedur-Ausfuehrung (MVP-026) ──────────────────────
    case ProcedureRunView = 'procedure.run.view';
    case ProcedureRunStart = 'procedure.run.start';
    case ProcedureRunExecute = 'procedure.run.execute';
    case ProcedureRunAbort = 'procedure.run.abort';

        // ── Backup-Nachweise (MVP-027) ──────────────────────────
    case ProcedureBackupRegister = 'procedure.backup.register';
    case ProcedureBackupVerify = 'procedure.backup.verify';
    case ProcedureBackupViewExternal = 'procedure.backup.viewExternal';
        // ── Vier-Augen / Zweite Person (MVP-028) ────────────────
    case ProcedureSecondPersonRequest = 'procedure.secondPerson.request';
    case ProcedureSecondPersonTake = 'procedure.secondPerson.take';
    case ProcedureSecondPersonSign = 'procedure.secondPerson.sign';
    case ProcedureSecondPersonRevoke = 'procedure.secondPerson.revoke';
        // ── Abweichungen / Folgeaktion (MVP-029) ────────────────
    case ProcedureDeviationRecord = 'procedure.deviation.record';
    case ProcedureDeviationAcceptRisk = 'procedure.deviation.acceptRisk';
    case ProcedureDeviationUpdate = 'procedure.deviation.update';
    case ProcedureDeviationView = 'procedure.deviation.view';
        // ── Klassifikationen (MVP-030) ──────────────────────────
    case ClassificationList = 'classification.list';
    case ClassificationOrgView = 'classification.org.view';
    case ClassificationOrgManage = 'classification.org.manage';
    case ClassificationOrgDeactivateDefault = 'classification.org.deactivateDefault';
    case ClassificationOrgImport = 'classification.org.import';
    case ClassificationPlatformManage = 'classification.platform.manage';
    case ClassificationRequirementView = 'classification.requirement.view';
    case ClassificationRequirementManage = 'classification.requirement.manage';
    case BranchProfileInstall = 'branchProfile.install';
    case BranchProfileViewCatalog = 'branchProfile.viewCatalog';
    case BranchProfileUninstall = 'branchProfile.uninstall';
        // ── Assets / Objektstammdaten (MVP-035) ───────────────
    case AssetView = 'asset.view';
    case AssetCreate = 'asset.create';
    case AssetUpdate = 'asset.update';
    case AssetDecommission = 'asset.decommission';
    case AssetTransferOwnership = 'asset.transferOwnership';
    case AssetCheckout = 'asset.checkout';
    case AssetDefectManage = 'asset.defect.manage';
        // ── Genehmigungs-Register (Veranstalter-Permits) ──────
    case PermitViewAny = 'permit.viewAny';
    case PermitView = 'permit.view';
    case PermitCreate = 'permit.create';
    case PermitUpdate = 'permit.update';
    case PermitDelete = 'permit.delete';
        // ── ServiceTicket-Workflow (FM-Tickets mit SLA) ───────
    case ServiceTicketView = 'serviceTicket.view';
    case ServiceTicketCreate = 'serviceTicket.create';
    case ServiceTicketUpdate = 'serviceTicket.update';
    case ServiceTicketAssign = 'serviceTicket.assign';
    case ServiceTicketClose = 'serviceTicket.close';
    case SlaContractView = 'slaContract.view';
    case SlaContractManage = 'slaContract.manage';
        // ── Helpdesk/Service Desk (Feature 065) ───────────────
    case HelpdeskQueueManage = 'helpdesk.queue.manage';
    case HelpdeskTicketInternalNote = 'helpdesk.ticket.internal_note';
        // ── Servicekatalog & Request-Genehmigungen (Feature 065, MVP-154) ──
    case ServiceCatalogManage = 'service_catalog.manage';
    case ServiceRequestApprove = 'service_request.approve';
        // ── Problem-Management (Feature 065, MVP-156) ─────────
    case ServiceDeskProblemManage = 'service_desk.problem.manage';
        // ── Change-/CAB-Management (Feature 065, MVP-157) ─────
        // Freigaben laufen über die gemeinsame Inbox mit
        // service_request.approve — bewusst KEIN eigenes approve-Recht.
    case ServiceDeskChangeManage = 'service_desk.change.manage';
        // ── SLA-Status/-Verletzungen & Report (Feature 010) ───
    case SlaViewAny = 'sla.viewAny';
    case SlaManage = 'sla.manage';
        // ── KeyHandover (Schlüsselverwaltung) ─────────────────
    case KeyHandoverView = 'keyHandover.view';
    case KeyHandoverRecord = 'keyHandover.record';
        // ── MeterReading (Zählerstände) ───────────────────────
    case MeterReadingView = 'meterReading.view';
    case MeterReadingRecord = 'meterReading.record';
        // ── Customer-Portal (Rolle `kunde`) ─────────────────────
    case CustomerPortalAccess = 'customerPortal.access';
    case CustomerPortalDiaryView = 'customerPortal.diary.view';
    case CustomerPortalTimeEntryView = 'customerPortal.timeEntry.view';
    case CustomerPortalInvoiceView = 'customerPortal.invoice.view';
    case CustomerPortalOpenIssueView = 'customerPortal.openIssue.view';
        // ── Kunden-Rückfragen (Feature 012, intern) ─────────────
    case ProtocolCustomerQueryManage = 'protocol.customerQuery.manage';
        // ── Webhooks / Integrationen (Feature 008) ──────────────
    case WebhookViewAny = 'webhook.viewAny';
    case WebhookManage = 'webhook.manage';
        // ── Artikelstamm (Feature 048, MVP-060) ─────────────────
        // ArticleViewAny / ArticleLexofficeSync existieren bereits oben
        // (Lexoffice-Artikel-Cache); hier nur die neuen Detail-/Pflegerechte.
    case ArticleView = 'article.view';
    case ArticleManage = 'article.manage';
    case ArticleImport = 'article.import';
        // ── Produktmodell (MVP-369): Typ-Ebene Hersteller-Modell ─────────
    case ProductViewAny = 'product.viewAny';
    case ProductManage = 'product.manage';
        // ── Lagerwirtschaft (Feature 048, MVP-066/067) ──────────
    case InventoryViewAny = 'inventory.viewAny';
    case InventoryPost = 'inventory.post';
    case InventoryConfigure = 'inventory.configure';
        // Vollaudit 2026-07 (M22): getrennte Freigaben — negative Bestände und
        // Ersatzmaterial-Genehmigung sind eigene, rollenbasierte Rechte.
    case InventoryNegative = 'inventory.negative';
    case InventorySubstituteApprove = 'inventory.substituteApprove';

        // ── Bewerbungen & Ausschreibungen (Feature 068, MVP-183) ──────────
        // Zwei GETRENNTE Rechtebereiche: Auftragsbewerbungen (tender.*) und
        // Personalbewerbungen (recruiting.*) — Bewerberdaten sind abgeschottet.
    case TenderViewAny = 'tender.viewAny';
    case TenderView = 'tender.view';
    case TenderManage = 'tender.manage';
    case TenderDecide = 'tender.decide';
    case RecruitingViewAny = 'recruiting.viewAny';
    case RecruitingView = 'recruiting.view';
    case RecruitingManage = 'recruiting.manage';
    case RecruitingDecide = 'recruiting.decide';
    case RecruitingPrivacy = 'recruiting.privacy';

        // ── Investitionsplanung (Feature 069, MVP-199) ──────────
    case InvestmentViewAny = 'investment.viewAny';
    case InvestmentView = 'investment.view';
    case InvestmentManage = 'investment.manage';
    case InvestmentApprove = 'investment.approve';

        // ── Notfall-/Krisenmanagement (Feature 070, MVP-211) ──────────
    case CrisisViewAny = 'crisis.viewAny';
    case CrisisView = 'crisis.view';
    case CrisisManage = 'crisis.manage';
    case CrisisApprove = 'crisis.approve';

        // ── Nachhaltigkeit/ESG (Feature 071, MVP-223) ──────────
    case SustainabilityViewAny = 'sustainability.viewAny';
    case SustainabilityView = 'sustainability.view';
    case SustainabilityManage = 'sustainability.manage';

        // ── Reklamation/Gewährleistung (Feature 072, MVP-246) ──────────
        // Getrennte Rollen: manage/decide/finance/warehouse/recourse.
    case ClaimViewAny = 'claim.viewAny';
    case ClaimView = 'claim.view';
    case ClaimManage = 'claim.manage';
    case ClaimDecide = 'claim.decide';
    case ClaimFinance = 'claim.finance';
    case ClaimWarehouse = 'claim.warehouse';
    case ClaimRecourse = 'claim.recourse';

        // ── Gemeinsames Asset-Sperrmodell (D12, Phasen 25–27) ──────────
        // Ausnahmefreigaben (blockOverride) sind befristet, begründet und
        // auditiert — bewusst getrennt von block/unblock.
    case AssetBlockManage = 'asset.block.manage';
    case AssetBlockOverride = 'asset.block.override';

        // ── Geräte-/Maschinenverleih (Feature 073, MVP-258) ────────────
        // handover deckt Übergabe UND Rücknahme (operative Ausgabe),
        // finance die kaufmännische Folge inkl. Kaution, rates die
        // versionierten Preislisten (D10).
    case RentalViewAny = 'rental.viewAny';
    case RentalView = 'rental.view';
    case RentalManage = 'rental.manage';
    case RentalHandover = 'rental.handover';
    case RentalFinance = 'rental.finance';
    case RentalRates = 'rental.rates';

        // ── Leasing/Finanzierung/Asset-Verträge (Feature 074, MVP-270) ─
        // finance schützt vertrauliche Konditionen (Raten, Restwerte,
        // Optionen); view sieht die Akte ohne Beträge.
    case AssetFinanceViewAny = 'assetFinance.viewAny';
    case AssetFinanceView = 'assetFinance.view';
    case AssetFinanceManage = 'assetFinance.manage';
    case AssetFinanceFinance = 'assetFinance.finance';

        // ── Prüfmittel/Eichung/Kalibrierung (Feature 075, MVP-282) ─────
        // inspect erfasst Prüfprotokolle/Zertifikate, release vergibt
        // befristete Ausnahmefreigaben (nutzt das D12-Sperrmodell).
    case AssetComplianceViewAny = 'assetCompliance.viewAny';
    case AssetComplianceView = 'assetCompliance.view';
    case AssetComplianceManage = 'assetCompliance.manage';
    case AssetComplianceInspect = 'assetCompliance.inspect';
    case AssetComplianceRelease = 'assetCompliance.release';

        // ── Allgemeine Vertragsverwaltung (Welle D, CLM) ───────────────
        // Verträge beliebiger Art mit Laufzeit/Kündigung/Indexierung und
        // Vertragskalender (Obligationen). Additiv zum Leasing-Modell.
    case ContractViewAny = 'contract.viewAny';
    case ContractView = 'contract.view';
    case ContractManage = 'contract.manage';

        // ── Domainverwaltung / DomainReselling (Feature 083, MVP-384–396) ──
        // Getrennte Rechte je Risikoklasse; register/contact/dns/renewal/
        // transfer sind eigene Aktionen, dangerous.approve ist Vier-Augen.
        // invoice.* bleiben inaktiv, bis ein realer Vertrag die Capability belegt.
    case DomainProviderView = 'domain.provider.view';
    case DomainProviderManage = 'domain.provider.manage';
    case DomainViewAny = 'domain.viewAny';
    case DomainView = 'domain.view';
    case DomainCustomerAssign = 'domain.customer.assign';
    case DomainRegister = 'domain.register';
    case DomainContactManage = 'domain.contact.manage';
    case DomainDnsManage = 'domain.dns.manage';
    case DomainRenewalManage = 'domain.renewal.manage';
    case DomainTransferManage = 'domain.transfer.manage';
    case DomainDangerousApprove = 'domain.dangerous.approve';
    case DomainAccountingView = 'domain.accounting.view';
    case DomainInvoiceView = 'domain.invoice.view';
    case DomainInvoiceDownload = 'domain.invoice.download';

        // ── KI-Assistenz (Feature 025, MVP-398–401) ──
        // manage = Provider-Verbindungen und Capability-Routing verwalten
        // (Admin-UI folgt in MVP-400); use = KI-Vorschläge anfordern —
        // Capability-Consumer (z. B. Feature 084) prüfen zusätzlich ihre
        // Fachrechte.
    case AiManage = 'ai.manage';
    case AiUse = 'ai.use';

    public function label(): string {
        // Slugs enthalten Punkte — __()/trans() würde sie als verschachtelten
        // Pfad lesen; die Übersetzungen liegen flach unter `access.permission`.
        // Daher das Array selbst auflösen.
        $translations = (array) trans('access.permission');

        return (string) ($translations[$this->value] ?? $this->value);
    }

    public function group(): PermissionGroup {
        return match (true) {
            str_starts_with($this->value, 'access.'), str_starts_with($this->value, 'audit-log.') => PermissionGroup::Access,
            str_starts_with($this->value, 'organization.'), str_starts_with($this->value, 'branding.'), str_starts_with($this->value, 'documentDesign.'), str_starts_with($this->value, 'org.onboarding.'), str_starts_with($this->value, 'privacy.'), str_starts_with($this->value, 'support.') => PermissionGroup::Organization,
            str_starts_with($this->value, 'team.') => PermissionGroup::Teams,
            str_starts_with($this->value, 'user.') => PermissionGroup::Members,
            str_starts_with($this->value, 'customer.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'foreignCustomer.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'supplier.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'permit.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'project.'), str_starts_with($this->value, 'task.'), str_starts_with($this->value, 'milestone.'), str_starts_with($this->value, 'agile.') => PermissionGroup::Projects,
            str_starts_with($this->value, 'timeEntry.'), str_starts_with($this->value, 'project-time.') => PermissionGroup::TimeEntries,
            str_starts_with($this->value, 'timesheet.') => PermissionGroup::Timesheets,
            str_starts_with($this->value, 'invoice.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'finance.') => PermissionGroup::Finance,
            str_starts_with($this->value, 'investment.') => PermissionGroup::Finance,
            str_starts_with($this->value, 'article.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'inventory.') => PermissionGroup::MasterData,
            str_starts_with($this->value, 'voucher.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'diary.') => PermissionGroup::Diary,
            str_starts_with($this->value, 'dutyPlan.'),
            str_starts_with($this->value, 'shift.'),
            str_starts_with($this->value, 'coverage-requirement.'),
            str_starts_with($this->value, 'on-call-shift.'),
            str_starts_with($this->value, 'emergency-assignment.'),
            str_starts_with($this->value, 'shift-type.'),
            str_starts_with($this->value, 'scheduled-shift.'),
            str_starts_with($this->value, 'availability.'),
            str_starts_with($this->value, 'staffing.'),
            str_starts_with($this->value, 'dispatch.') => PermissionGroup::Scheduling,
            str_starts_with($this->value, 'vacation.'), str_starts_with($this->value, 'sick-leave.') => PermissionGroup::Absences,
            str_starts_with($this->value, 'tour.'),
            str_starts_with($this->value, 'vehicle.'),
            str_starts_with($this->value, 'travel-log.'),
            str_starts_with($this->value, 'energy-log.'),
            str_starts_with($this->value, 'passenger.') => PermissionGroup::Fleet,
            str_starts_with($this->value, 'report.') => PermissionGroup::Reports,
            str_starts_with($this->value, 'attendance.'),
            str_starts_with($this->value, 'work-schedule.'),
            str_starts_with($this->value, 'surchargeRule.'),
            str_starts_with($this->value, 'costCenterRule.'),
            str_starts_with($this->value, 'wageTypeMapping.'),
            str_starts_with($this->value, 'compliance.'),
            str_starts_with($this->value, 'flex.') => PermissionGroup::WorkingTime,
            str_starts_with($this->value, 'safety.') => PermissionGroup::Safety,
            str_starts_with($this->value, 'openIssue.') => PermissionGroup::OpenIssues,
            str_starts_with($this->value, 'serviceTicket.') => PermissionGroup::OpenIssues,
            str_starts_with($this->value, 'helpdesk.') => PermissionGroup::OpenIssues,
            // Service Desk (Feature 065, MVP-154+): ohne dieses Mapping fielen
            // die Präfixe in den default => MasterData (Stolperfalle A3-Plan).
            str_starts_with($this->value, 'service_catalog.'),
            str_starts_with($this->value, 'service_request.'),
            str_starts_with($this->value, 'service_desk.') => PermissionGroup::OpenIssues,
            str_starts_with($this->value, 'notificationRule.') => PermissionGroup::Organization,
            str_starts_with($this->value, 'webhook.') => PermissionGroup::Organization,
            str_starts_with($this->value, 'communication.') => PermissionGroup::Communication,
            str_starts_with($this->value, 'document.') => PermissionGroup::Documents,
            str_starts_with($this->value, 'knowledge.') => PermissionGroup::Knowledge,
            str_starts_with($this->value, 'ideas.') => PermissionGroup::Ideas,
            str_starts_with($this->value, 'isms.') => PermissionGroup::Isms,
            str_starts_with($this->value, 'formTemplate.'), str_starts_with($this->value, 'formSubmission.') => PermissionGroup::Forms,
            str_starts_with($this->value, 'slaContract.') => PermissionGroup::Customers,
            // Hinweis: nach slaContract.* prüfen — sonst würde 'slaContract.*'
            // fälschlich über den 'sla.'-Präfix greifen.
            str_starts_with($this->value, 'sla.') => PermissionGroup::Reports,
            str_starts_with($this->value, 'protocol.') => PermissionGroup::Protocols,
            str_starts_with($this->value, 'externalParticipant.') => PermissionGroup::Protocols,
            str_starts_with($this->value, 'procedure.') => PermissionGroup::Procedures,
            str_starts_with($this->value, 'customerPortal.') => PermissionGroup::CustomerPortal,
            str_starts_with($this->value, 'tender.'), str_starts_with($this->value, 'recruiting.') => PermissionGroup::Applications,
            str_starts_with($this->value, 'crisis.') => PermissionGroup::Crisis,
            str_starts_with($this->value, 'sustainability.') => PermissionGroup::Sustainability,
            str_starts_with($this->value, 'claim.') => PermissionGroup::Claims,
            str_starts_with($this->value, 'domain.') => PermissionGroup::Domains,
            str_starts_with($this->value, 'rental.') => PermissionGroup::Rental,
            str_starts_with($this->value, 'assetFinance.') => PermissionGroup::AssetFinance,
            str_starts_with($this->value, 'assetCompliance.') => PermissionGroup::AssetCompliance,
            str_starts_with($this->value, 'contract.') => PermissionGroup::Contracts,
            str_starts_with($this->value, 'platform.'), str_starts_with($this->value, 'metrics.'), str_starts_with($this->value, 'backup.'), str_starts_with($this->value, 'security.') => PermissionGroup::Platform,
            default => PermissionGroup::MasterData,
        };
    }

    /**
     * Liefert alle Permission-Werte gruppiert für die UI-Matrix.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array {
        $grouped = [];
        foreach (self::cases() as $permission) {
            $grouped[$permission->group()->value][] = $permission;
        }

        return $grouped;
    }
}
