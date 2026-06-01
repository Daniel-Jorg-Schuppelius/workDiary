<?php
/*
 * Created on   : Thu May 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PermissionsSeeder.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Database\Seeders;

use App\Enums\User\{Permission as PermissionEnum, UserRole};
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\{Permission, Role};
use Spatie\Permission\PermissionRegistrar;

/**
 * Legt alle in {@see PermissionEnum} definierten Permissions als globale
 * (team-unabhängige) Spatie-Permissions an und stellt für jede bestehende
 * Organisation die Default-Rollen mit sinnvollen Permission-Zuordnungen
 * bereit. Idempotent — kann beliebig oft ausgeführt werden.
 *
 * Rollen sind organisationsspezifisch (team_id = organization.id) und
 * werden ausschließlich hier definiert. Nutzer-Zuweisungen erfolgen über
 * die Admin-UI (Admin\Access\MemberController).
 */
class PermissionsSeeder extends Seeder {
    public function run(): void {
        /** @var PermissionRegistrar $registrar */
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        self::ensurePermissionsExist();

        // Globaler Plattform-Admin (team_id = null): erhält alle Permissions.
        // Der Plattform-Admin überspringt zusätzlich alle Policies via
        // HasAdminBypass-Trait; die Permission-Zuordnung dient hier nur der
        // Transparenz in der Admin-UI.
        $globalAdmin = Role::query()
            ->whereNull(config('permission.column_names.team_foreign_key', 'team_id'))
            ->where('name', UserRole::Admin->value)
            ->where('guard_name', 'web')
            ->first();

        if (! $globalAdmin instanceof Role) {
            $registrar->setPermissionsTeamId(null);
            /** @var Role $globalAdmin */
            $globalAdmin = Role::findOrCreate(UserRole::Admin->value, 'web');
        }

        $globalAdmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());

        // Pro bestehender Organisation die vier Default-Rollen anlegen.
        foreach (Organization::query()->orderBy('id')->get() as $organization) {
            $this->seedOrganization($organization, $registrar);
        }
    }

    /**
     * Legt die Default-Rollen einer einzelnen Organisation an. Wird zentral
     * vom Observer (siehe OrganizationObserver) und vom Seeder aufgerufen.
     */
    public static function seedOrganization(Organization $organization, ?PermissionRegistrar $registrar = null): void {
        $registrar ??= app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($organization->id);

        // Defense in Depth: bei Org-Creates über den OrganizationObserver
        // (z. B. in Tests, Tenant-Registrierung, frischer DB) wurde der
        // Haupt-PermissionsSeeder unter Umständen noch nicht ausgeführt
        // und die referenzierten Spatie-Permissions existieren noch nicht.
        // Ohne diese Sicherung würde syncPermissions() unten in eine
        // PermissionDoesNotExist-Exception laufen und Org-Anlage scheitern.
        self::ensurePermissionsExist();

        $teamForeign = config('permission.column_names.team_foreign_key', 'team_id');
        $rolesAndPermissions = self::defaultRoleMatrix();

        foreach ($rolesAndPermissions as $roleName => $permissions) {
            // Spatie's Role::findOrCreate würde auf eine bestehende globale
            // Rolle (team_id = NULL) zurückfallen. Wir wollen pro Organisation
            // eine eigene Rollen-Instanz und müssen daher direkt mit
            // firstOrCreate gegen das volle Attribute-Set arbeiten.
            /** @var Role $role */
            $role = Role::query()->firstOrCreate([
                $teamForeign => $organization->id,
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                array_map(static fn(PermissionEnum $p): string => $p->value, $permissions)
            );
        }
    }

    /**
     * Stellt sicher, dass alle in {@see PermissionEnum} definierten
     * Permissions auf dem 'web'-Guard existieren. Idempotent. Wird vom
     * Haupt-`run()` und vom Observer-Pfad gleichermaßen verwendet, damit
     * Org-Erstellungen vor dem ersten Permissions-Seeding nicht in eine
     * `PermissionDoesNotExist`-Exception laufen.
     *
     * Fast-Path: Sind bereits ebenso viele 'web'-Permissions vorhanden wie
     * Enum-Cases, überspringen wir die ~138 findOrCreate-Queries. Das ist
     * für Tests relevant, in denen pro setUp() eine neue Organization
     * angelegt wird und der Observer-Pfad sonst jedes Mal die volle Liste
     * durchwalken würde.
     */
    private static function ensurePermissionsExist(): void {
        $expected = count(PermissionEnum::cases());
        $existing = Permission::query()->where('guard_name', 'web')->count();

        if ($existing >= $expected) {
            return;
        }

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }
    }

    /**
     * Default-Mapping Rolle → Permissions. Die Plattform-Admin-Rolle wird
     * zusätzlich global (team_id = null) angelegt; hier deckt der Eintrag
     * den Org-Admin ab, der innerhalb seiner Organisation alles darf.
     *
     * Profile gemäß Feature 019 / MVP-003:
     * - admin (Kundenadmin): alles innerhalb der Org
     * - geschaeftsfuehrung: read-only über alle Bereiche + Reports + Audit
     * - teamleitung: Mitarbeiter-, Zeit- und Planungsführung, ohne Finanzen
     * - buchhaltung: Kunden, Rechnungen, Stundenzettel, Auswertungen
     * - user (Innendienst): Standard-Mitarbeiter (eigene Zeit, Diary, Urlaub)
     * - aussendienst: mobile Erfassung (eigene Zeit, Diary, Touren, Spesen)
     * - callcenter: Tagebuch und Kundendaten ansehen
     * - support: Anbieter-Support, ausschließlich read-only + Audit
     *
     * @return array<string, list<PermissionEnum>>
     */
    private static function defaultRoleMatrix(): array {
        $all = PermissionEnum::cases();

        // Geschäftsführung: read-only über alle Bereiche, Reports + Audit.
        $geschaeftsfuehrung = array_filter(
            PermissionEnum::cases(),
            static function (PermissionEnum $p): bool {
                $value = $p->value;
                if (str_ends_with($value, '.viewAny') || str_ends_with($value, '.view') || str_ends_with($value, '.viewOwn')) {
                    return true;
                }

                return in_array($value, [
                    PermissionEnum::OrganizationView->value,
                    PermissionEnum::ReportView->value,
                    PermissionEnum::ReportExport->value,
                    PermissionEnum::AuditLogView->value,
                    PermissionEnum::AccessAuditView->value,
                    PermissionEnum::FlexBalanceView->value,
                    PermissionEnum::ClassificationList->value,
                    // MVP-005: Datenschutzbericht als PDF/Export — die
                    // .view-Heuristik trifft hier nicht, daher explizit.
                    PermissionEnum::PrivacyReportExport->value,
                    // MVP-016/017: Monats-/Korrektur-Lesezugriff — die
                    // .view-Heuristik trifft auf `month.view.*`/`correction.view.*`
                    // nicht, daher explizit.
                    PermissionEnum::MonthViewOrganization->value,
                    PermissionEnum::CorrectionViewOrganization->value,
                ], true);
            }
        );

        // Teamleitung: operative Führung (Personal, Zeit, Plan), ohne Finanzen.
        $teamleitung = [
            PermissionEnum::OrganizationView,
            PermissionEnum::NumberFormatManage,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::UserFlexManage,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::ProjectCreate,
            PermissionEnum::ProjectUpdate,
            PermissionEnum::ProjectArchive,
            PermissionEnum::TaskManage,
            PermissionEnum::MilestoneManage,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimeEntryApprove,
            PermissionEnum::TimeEntryCreateForOthers,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::TimesheetSign,
            PermissionEnum::TimesheetLock,
            PermissionEnum::TimesheetUnlock,
            PermissionEnum::MonthViewTeam,
            PermissionEnum::MonthApprove,
            PermissionEnum::MonthReject,
            PermissionEnum::MonthReopen,
            PermissionEnum::MonthLock,
            PermissionEnum::CorrectionViewTeam,
            PermissionEnum::CorrectionApprove,
            PermissionEnum::CorrectionReject,
            PermissionEnum::ExportTimeCreate,
            PermissionEnum::ExportTimeDeliver,
            PermissionEnum::ExportTimeDelete,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryCreateForOthers,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryExport,
            PermissionEnum::DutyPlanViewAny,
            PermissionEnum::DutyPlanCreate,
            PermissionEnum::DutyPlanUpdate,
            PermissionEnum::DutyPlanPublish,
            PermissionEnum::ShiftManage,
            PermissionEnum::ScheduledShiftManage,
            PermissionEnum::CoverageRequirementManage,
            PermissionEnum::OnCallShiftManage,
            PermissionEnum::EmergencyAssignmentManage,
            PermissionEnum::ShiftTypeManage,
            PermissionEnum::VacationViewAny,
            PermissionEnum::VacationApprove,
            PermissionEnum::VacationCancel,
            PermissionEnum::SickLeaveViewAny,
            PermissionEnum::SickLeaveManage,
            PermissionEnum::AttendanceViewAny,
            PermissionEnum::AttendanceManage,
            PermissionEnum::WorkScheduleManage,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::FlexBalanceManage,
            PermissionEnum::TourViewAny,
            PermissionEnum::TourManage,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            PermissionEnum::OpenIssueAssign,
            PermissionEnum::OpenIssuePublishToCustomer,
            PermissionEnum::OpenIssueDelete,
            PermissionEnum::ServiceTicketView,
            PermissionEnum::ServiceTicketCreate,
            PermissionEnum::ServiceTicketUpdate,
            PermissionEnum::ServiceTicketAssign,
            PermissionEnum::ServiceTicketClose,
            PermissionEnum::SlaContractView,
            PermissionEnum::SlaContractManage,
            PermissionEnum::KeyHandoverView,
            PermissionEnum::KeyHandoverRecord,
            PermissionEnum::MeterReadingView,
            PermissionEnum::MeterReadingRecord,
            PermissionEnum::ProtocolViewAny,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolRequestReview,
            PermissionEnum::ProtocolSignInternal,
            PermissionEnum::ProtocolSignCustomer,
            PermissionEnum::ProtocolArchive,
            PermissionEnum::ProtocolSupersede,
            PermissionEnum::ProtocolDelete,
            PermissionEnum::ProtocolSignatureRequest,
            PermissionEnum::ProtocolPdfDownload,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ProtocolItemPhotoViewGeo,
            PermissionEnum::ProcedureTemplateView,
            PermissionEnum::ProcedureTemplateCreate,
            PermissionEnum::ProcedureTemplateUpdate,
            PermissionEnum::ProcedureTemplatePublish,
            PermissionEnum::ProcedureRunView,
            PermissionEnum::ProcedureRunStart,
            PermissionEnum::ProcedureRunExecute,
            PermissionEnum::ProcedureRunAbort,
            PermissionEnum::ProcedureBackupRegister,
            PermissionEnum::ProcedureBackupVerify,
            PermissionEnum::ProcedureBackupViewExternal,
            PermissionEnum::ProcedureSecondPersonRequest,
            PermissionEnum::ProcedureSecondPersonTake,
            PermissionEnum::ProcedureSecondPersonSign,
            PermissionEnum::ProcedureSecondPersonRevoke,
            PermissionEnum::ProcedureDeviationRecord,
            PermissionEnum::ProcedureDeviationAcceptRisk,
            PermissionEnum::ProcedureDeviationUpdate,
            PermissionEnum::ProcedureDeviationView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationOrgManage,
            PermissionEnum::ClassificationOrgDeactivateDefault,
            PermissionEnum::ClassificationOrgImport,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::ClassificationRequirementManage,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
            PermissionEnum::AssetDecommission,
            PermissionEnum::AssetTransferOwnership,
            PermissionEnum::ReportView,
            PermissionEnum::AccessAuditView,
        ];

        $buchhaltung = [
            PermissionEnum::OrganizationView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::CustomerCreate,
            PermissionEnum::CustomerUpdate,
            PermissionEnum::CustomerDelete,
            PermissionEnum::CustomerExport,
            PermissionEnum::CustomerImport,
            PermissionEnum::CustomerLexofficeSync,
            PermissionEnum::ForeignCustomerViewAny,
            PermissionEnum::ForeignCustomerView,
            PermissionEnum::ForeignCustomerCreate,
            PermissionEnum::ForeignCustomerUpdate,
            PermissionEnum::ForeignCustomerDelete,
            PermissionEnum::ForeignCustomerPromote,
            PermissionEnum::SupplierViewAny,
            PermissionEnum::SupplierView,
            PermissionEnum::SupplierCreate,
            PermissionEnum::SupplierUpdate,
            PermissionEnum::SupplierDelete,
            PermissionEnum::SupplierExport,
            PermissionEnum::SupplierImport,
            PermissionEnum::SupplierLexofficeSync,
            PermissionEnum::ArticleViewAny,
            PermissionEnum::ArticleLexofficeSync,
            PermissionEnum::VoucherViewAny,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::ProjectManageBilling,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimeEntryApprove,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::TimesheetSign,
            PermissionEnum::TimesheetLock,
            PermissionEnum::TimesheetUnlock,
            PermissionEnum::TimesheetExport,
            PermissionEnum::MonthViewOrganization,
            PermissionEnum::CorrectionViewOrganization,
            PermissionEnum::InvoiceViewAny,
            PermissionEnum::InvoiceView,
            PermissionEnum::InvoiceCreate,
            PermissionEnum::InvoiceUpdate,
            PermissionEnum::InvoiceDelete,
            PermissionEnum::InvoiceIssue,
            PermissionEnum::InvoicePay,
            PermissionEnum::InvoiceExport,
            PermissionEnum::ReportView,
            PermissionEnum::ReportExport,
            PermissionEnum::AuditLogView,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::UserManageRates,
            PermissionEnum::UserFlexManage,
        ];

        $user = [
            PermissionEnum::OrganizationView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TaskManage,
            PermissionEnum::MilestoneManage,
            PermissionEnum::TimeEntryViewOwn,
            PermissionEnum::TimeEntryCreate,
            PermissionEnum::TimeEntryUpdate,
            PermissionEnum::TimeEntryDelete,
            PermissionEnum::TimesheetCreate,
            PermissionEnum::TimesheetUpdate,
            PermissionEnum::TimesheetSign,
            PermissionEnum::MonthViewOwn,
            PermissionEnum::MonthSubmitOwn,
            PermissionEnum::CorrectionCreateOwn,
            PermissionEnum::CorrectionSubmitOwn,
            PermissionEnum::CorrectionWithdrawOwn,
            PermissionEnum::DiaryViewOwn,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryDelete,
            PermissionEnum::VacationRequest,
            PermissionEnum::AttendanceManage,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::TravelLogManage,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ProcedureTemplateView,
            PermissionEnum::ProcedureRunView,
            PermissionEnum::ProcedureRunStart,
            PermissionEnum::ProcedureRunExecute,
            PermissionEnum::ProcedureBackupRegister,
            PermissionEnum::ProcedureSecondPersonRequest,
            PermissionEnum::ProcedureSecondPersonSign,
            PermissionEnum::ProcedureDeviationRecord,
            PermissionEnum::ProcedureDeviationView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
        ];

        // Außendienst: schlanker als user, dafür mit vollem Touren-/Spesen-
        // Funktionsumfang und KEINER Mitarbeiter-/Finanz-/Planungssicht.
        $aussendienst = [
            PermissionEnum::OrganizationView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TaskManage,
            PermissionEnum::TimeEntryViewOwn,
            PermissionEnum::TimeEntryCreate,
            PermissionEnum::TimeEntryUpdate,
            PermissionEnum::TimeEntryDelete,
            PermissionEnum::TimesheetCreate,
            PermissionEnum::TimesheetUpdate,
            PermissionEnum::TimesheetSign,
            PermissionEnum::MonthViewOwn,
            PermissionEnum::MonthSubmitOwn,
            PermissionEnum::CorrectionCreateOwn,
            PermissionEnum::CorrectionSubmitOwn,
            PermissionEnum::CorrectionWithdrawOwn,
            PermissionEnum::DiaryViewOwn,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::DiaryDelete,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::TravelLogManage,
            PermissionEnum::VehicleViewAny,
            PermissionEnum::EnergyLogManage,
            PermissionEnum::VacationRequest,
            PermissionEnum::AttendanceManage,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::OpenIssueViewAny,
            PermissionEnum::OpenIssueView,
            PermissionEnum::OpenIssueCreate,
            PermissionEnum::OpenIssueUpdate,
            PermissionEnum::ProtocolView,
            PermissionEnum::ProtocolCreate,
            PermissionEnum::ProtocolEditDraft,
            PermissionEnum::ProtocolRequestReview,
            PermissionEnum::ProtocolSignInternal,
            PermissionEnum::ProtocolPdfDownload,
            PermissionEnum::ProtocolItemPhotoAdd,
            PermissionEnum::ProtocolItemPhotoRemove,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            PermissionEnum::AssetCreate,
            PermissionEnum::AssetUpdate,
        ];

        $callcenter = [
            PermissionEnum::OrganizationView,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryCreateForOthers,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
        ];

        // Support (Anbieter-Support): strikt read-only über fast alle Bereiche
        // plus Auditzugriff. KEINE Create/Update/Delete-Permissions.
        $support = [
            PermissionEnum::OrganizationView,
            PermissionEnum::UserViewAny,
            PermissionEnum::UserView,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
            PermissionEnum::ProjectViewAny,
            PermissionEnum::ProjectView,
            PermissionEnum::TimeEntryViewAny,
            PermissionEnum::TimesheetViewAny,
            PermissionEnum::InvoiceViewAny,
            PermissionEnum::InvoiceView,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DutyPlanViewAny,
            PermissionEnum::VacationViewAny,
            PermissionEnum::SickLeaveViewAny,
            PermissionEnum::AttendanceViewAny,
            PermissionEnum::TourViewAny,
            PermissionEnum::TravelLogViewAny,
            PermissionEnum::VehicleViewAny,
            PermissionEnum::ReportView,
            PermissionEnum::AuditLogView,
            PermissionEnum::AccessAuditView,
            PermissionEnum::FlexBalanceView,
            PermissionEnum::ClassificationList,
            PermissionEnum::ClassificationOrgView,
            PermissionEnum::ClassificationRequirementView,
            PermissionEnum::AssetView,
            // Datenschutzseite (MVP-005): Support sieht die Seite read-only,
            // ohne Widerruf-Knöpfe. Die *.view-Permissions liegen für die
            // Geschäftsführung bereits implizit über die `.view`-Heuristik;
            // Support hat keine Heuristik, daher explizit.
            PermissionEnum::PrivacyView,
            PermissionEnum::PrivacySessionsView,
            PermissionEnum::PrivacyTokensView,
            PermissionEnum::PrivacyIntegrationsView,
            PermissionEnum::PrivacyExportsView,
            PermissionEnum::PrivacySupportView,
        ];

        // Rolle `kunde`: read-only Zugriff auf das Customer-Portal, ausschliesslich
        // auf die EIGENEN Datensaetze des verknuepften Kunden. Wird vom
        // `customer`-Guard ausgewertet; interne Routen sind durch den separaten
        // Provider technisch nicht erreichbar.
        $kunde = [
            PermissionEnum::CustomerPortalAccess,
            PermissionEnum::CustomerPortalDiaryView,
            PermissionEnum::CustomerPortalTimeEntryView,
            PermissionEnum::CustomerPortalInvoiceView,
            PermissionEnum::CustomerPortalOpenIssueView,
        ];

        // Lese-Rollen, die Kunden sehen, sehen auch deren Fremdkunden.
        $foreignCustomerRead = [
            PermissionEnum::ForeignCustomerViewAny,
            PermissionEnum::ForeignCustomerView,
        ];

        return [
            UserRole::Admin->value => $all,
            UserRole::Geschaeftsfuehrung->value => $geschaeftsfuehrung,
            UserRole::Teamleitung->value => [...$teamleitung, ...$foreignCustomerRead],
            UserRole::Buchhaltung->value => $buchhaltung,
            UserRole::User->value => [...$user, ...$foreignCustomerRead],
            UserRole::Aussendienst->value => [...$aussendienst, ...$foreignCustomerRead],
            UserRole::Callcenter->value => [...$callcenter, ...$foreignCustomerRead],
            UserRole::Support->value => [...$support, ...$foreignCustomerRead],
            UserRole::Kunde->value => $kunde,
        ];
    }
}
