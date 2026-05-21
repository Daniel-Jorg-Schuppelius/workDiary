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

use App\Enums\User\Permission as PermissionEnum;
use App\Enums\User\UserRole;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
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

        foreach (PermissionEnum::cases() as $permission) {
            Permission::findOrCreate($permission->value, 'web');
        }

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
     * Default-Mapping Rolle → Permissions. Die Plattform-Admin-Rolle wird
     * zusätzlich global (team_id = null) angelegt; hier deckt der Eintrag
     * den Org-Admin ab, der innerhalb seiner Organisation alles darf.
     *
     * @return array<string, list<PermissionEnum>>
     */
    private static function defaultRoleMatrix(): array {
        $all = PermissionEnum::cases();

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
        ];

        $callcenter = [
            PermissionEnum::OrganizationView,
            PermissionEnum::DiaryViewAny,
            PermissionEnum::DiaryCreate,
            PermissionEnum::DiaryCreateForOthers,
            PermissionEnum::DiaryUpdate,
            PermissionEnum::CustomerViewAny,
            PermissionEnum::CustomerView,
        ];

        return [
            UserRole::Admin->value => $all,
            UserRole::Buchhaltung->value => $buchhaltung,
            UserRole::User->value => $user,
            UserRole::Callcenter->value => $callcenter,
        ];
    }
}
