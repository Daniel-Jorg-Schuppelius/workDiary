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

        // ── Kunden ─────────────────────────────────────────────────────────
    case CustomerViewAny = 'customer.viewAny';
    case CustomerView = 'customer.view';
    case CustomerCreate = 'customer.create';
    case CustomerUpdate = 'customer.update';
    case CustomerDelete = 'customer.delete';
    case CustomerExport = 'customer.export';
    case CustomerImport = 'customer.import';
    case CustomerLexofficeSync = 'customer.lexoffice.sync';

        // ── Projekte / Aufgaben / Meilensteine ─────────────────────────────
    case ProjectViewAny = 'project.viewAny';
    case ProjectView = 'project.view';
    case ProjectCreate = 'project.create';
    case ProjectUpdate = 'project.update';
    case ProjectDelete = 'project.delete';
    case ProjectArchive = 'project.archive';
    case ProjectManageBilling = 'project.billing.manage';
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
    case ActivityCategoryManage = 'activity-category.manage';
    case TagManage = 'tag.manage';
    case QualificationManage = 'qualification.manage';
    case EntryTypeManage = 'entry-type.manage';
    case HolidayManage = 'holiday.manage';

        // ── Reporting / Audit / Sonstiges ──────────────────────────────────
    case ReportView = 'report.view';
    case ReportExport = 'report.export';
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
            str_starts_with($this->value, 'organization.'), str_starts_with($this->value, 'branding.') => PermissionGroup::Organization,
            str_starts_with($this->value, 'user.') => PermissionGroup::Members,
            str_starts_with($this->value, 'customer.') => PermissionGroup::Customers,
            str_starts_with($this->value, 'project.'), str_starts_with($this->value, 'task.'), str_starts_with($this->value, 'milestone.') => PermissionGroup::Projects,
            str_starts_with($this->value, 'timeEntry.') => PermissionGroup::TimeEntries,
            str_starts_with($this->value, 'timesheet.') => PermissionGroup::Timesheets,
            str_starts_with($this->value, 'invoice.') => PermissionGroup::Invoicing,
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
            str_starts_with($this->value, 'protocol.') => PermissionGroup::Protocols,
            str_starts_with($this->value, 'customerPortal.') => PermissionGroup::CustomerPortal,
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
