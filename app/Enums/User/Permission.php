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
    case OrgOnboardingView = 'org.onboarding.view';
    case OrgOnboardingSkipStep = 'org.onboarding.skipStep';
    case OrgOnboardingDismissWidget = 'org.onboarding.dismissWidget';
    case NumberFormatManage = 'organization.numberFormat.manage';
        // ── Plattform-Diagnose (MVP-044) ────────────────────────────
    case PlatformDiagnosticsView = 'platform.diagnostics.view';
    case PlatformDiagnosticsRunCheck = 'platform.diagnostics.runCheck';
        // ── Plattform-Supportbericht (MVP-045) ──────────────────────
    case PlatformSupportExport = 'platform.support.export';
    case PlatformSupportExportWithSamples = 'platform.support.exportWithSamples';
        // ── Plattform-Lizenz (MVP-047) ──────────────────────────────
    case PlatformLicenseView = 'platform.license.view';
    case PlatformLicenseInstall = 'platform.license.install';
    case PlatformFeatureFlagOverride = 'platform.featureFlag.override';
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

        // ── Zeit-Export / Lohnübergabe (MVP-019) ───────────────────────────
    case ExportTimeCreate = 'export.time.create';
    case ExportTimeDeliver = 'export.time.deliver';
    case ExportTimeDelete = 'export.time.delete';

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

        // ── Abwesenheiten ──────────────────────────────────────────────────
    case VacationViewAny = 'vacation.viewAny';
    case VacationRequest = 'vacation.request';
    case VacationApprove = 'vacation.approve';
    case VacationCancel = 'vacation.cancel';
    case SickLeaveViewAny = 'sick-leave.viewAny';
    case SickLeaveManage = 'sick-leave.manage';

        // ── Touren / Fuhrpark ──────────────────────────────────────────────
    case TourViewAny = 'tour.viewAny';
    case TourManage = 'tour.manage';
    case VehicleViewAny = 'vehicle.viewAny';
    case VehicleManage = 'vehicle.manage';
    case TravelLogViewAny = 'travel-log.viewAny';
    case TravelLogManage = 'travel-log.manage';
    case EnergyLogManage = 'energy-log.manage';

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
    case ImportViewReports = 'import.viewReports';
    case AuditLogView = 'audit-log.view';
    case AttendanceViewAny = 'attendance.viewAny';
    case AttendanceManage = 'attendance.manage';
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
        // ── ServiceTicket-Workflow (FM-Tickets mit SLA) ───────
    case ServiceTicketView = 'serviceTicket.view';
    case ServiceTicketCreate = 'serviceTicket.create';
    case ServiceTicketUpdate = 'serviceTicket.update';
    case ServiceTicketAssign = 'serviceTicket.assign';
    case ServiceTicketClose = 'serviceTicket.close';
    case SlaContractView = 'slaContract.view';
    case SlaContractManage = 'slaContract.manage';
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

    public function label(): string {
        // Permission-Slugs enthalten Punkte (z. B. "project.view") — Laravels
        // __() / trans() würde die als verschachtelten Pfad interpretieren und
        // den Lookup verfehlen, weil die Übersetzungen flach unter dem Key
        // `access.permission` liegen. Daher hier das Array selbst auflösen.
        $translations = (array) trans('access.permission');

        return (string) ($translations[$this->value] ?? $this->value);
    }

    public function group(): PermissionGroup {
        return match (true) {
            str_starts_with($this->value, 'access.'), str_starts_with($this->value, 'audit-log.') => PermissionGroup::Access,
            str_starts_with($this->value, 'organization.'), str_starts_with($this->value, 'branding.'), str_starts_with($this->value, 'org.onboarding.'), str_starts_with($this->value, 'privacy.') => PermissionGroup::Organization,
            str_starts_with($this->value, 'team.') => PermissionGroup::Teams,
            str_starts_with($this->value, 'user.') => PermissionGroup::Members,
            str_starts_with($this->value, 'customer.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'foreignCustomer.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'supplier.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'project.'), str_starts_with($this->value, 'task.'), str_starts_with($this->value, 'milestone.') => PermissionGroup::Projects,
            str_starts_with($this->value, 'timeEntry.') => PermissionGroup::TimeEntries,
            str_starts_with($this->value, 'timesheet.') => PermissionGroup::Timesheets,
            str_starts_with($this->value, 'invoice.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'article.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'voucher.') => PermissionGroup::Invoicing,
            str_starts_with($this->value, 'diary.') => PermissionGroup::Diary,
            str_starts_with($this->value, 'dutyPlan.'),
            str_starts_with($this->value, 'shift.'),
            str_starts_with($this->value, 'coverage-requirement.'),
            str_starts_with($this->value, 'on-call-shift.'),
            str_starts_with($this->value, 'emergency-assignment.'),
            str_starts_with($this->value, 'shift-type.'),
            str_starts_with($this->value, 'scheduled-shift.') => PermissionGroup::Scheduling,
            str_starts_with($this->value, 'vacation.'), str_starts_with($this->value, 'sick-leave.') => PermissionGroup::Absences,
            str_starts_with($this->value, 'tour.'),
            str_starts_with($this->value, 'vehicle.'),
            str_starts_with($this->value, 'travel-log.'),
            str_starts_with($this->value, 'energy-log.') => PermissionGroup::Fleet,
            str_starts_with($this->value, 'report.') => PermissionGroup::Reports,
            str_starts_with($this->value, 'attendance.'),
            str_starts_with($this->value, 'work-schedule.'),
            str_starts_with($this->value, 'flex.') => PermissionGroup::WorkingTime,
            str_starts_with($this->value, 'openIssue.') => PermissionGroup::OpenIssues,
            str_starts_with($this->value, 'serviceTicket.') => PermissionGroup::OpenIssues,
            str_starts_with($this->value, 'slaContract.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'protocol.') => PermissionGroup::Protocols,
            str_starts_with($this->value, 'procedure.') => PermissionGroup::Procedures,
            str_starts_with($this->value, 'customerPortal.') => PermissionGroup::CustomerPortal,
            str_starts_with($this->value, 'platform.') => PermissionGroup::Platform,
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
