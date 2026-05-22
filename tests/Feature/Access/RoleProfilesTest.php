<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RoleProfilesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Access;

use App\Enums\User\Permission as PermissionEnum;
use App\Enums\User\UserRole;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Smoke-Tests pro Rollenprofil aus MVP-003 / Feature 019.
 *
 * Stellt sicher, dass die in `PermissionsSeeder::defaultRoleMatrix()`
 * verankerten Profile genau die Rechte erhalten (positives Setup) und
 * NICHT versehentlich um schreibende Rechte erweitert werden, die
 * gemäß `docs/security/rollen-matrix.md` ausgeschlossen sind
 * (negative Absicherung).
 */
class RoleProfilesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        // Org-Kontext im Permission-Registrar aktivieren, damit
        // `hasPermissionTo()` die team-spezifischen Rollen findet.
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    /** @return array<string, array{0: string, 1: list<PermissionEnum>, 2: list<PermissionEnum>}> */
    public static function roleProfileProvider(): array {
        return [
            'geschaeftsfuehrung' => [
                UserRole::Geschaeftsfuehrung->value,
                // sollte können
                [
                    PermissionEnum::CustomerViewAny,
                    PermissionEnum::InvoiceView,
                    PermissionEnum::ReportView,
                    PermissionEnum::AuditLogView,
                ],
                // darf NICHT
                [
                    PermissionEnum::CustomerCreate,
                    PermissionEnum::InvoiceCreate,
                    PermissionEnum::ProjectCreate,
                ],
            ],
            'teamleitung' => [
                UserRole::Teamleitung->value,
                [
                    PermissionEnum::TimeEntryApprove,
                    PermissionEnum::DutyPlanPublish,
                    PermissionEnum::VacationApprove,
                    PermissionEnum::ProjectUpdate,
                ],
                [
                    PermissionEnum::InvoiceCreate,
                    PermissionEnum::CustomerCreate,
                    PermissionEnum::TimesheetExport,
                ],
            ],
            'aussendienst' => [
                UserRole::Aussendienst->value,
                [
                    PermissionEnum::TimeEntryCreate,
                    PermissionEnum::DiaryCreate,
                    PermissionEnum::TravelLogManage,
                    PermissionEnum::EnergyLogManage,
                    PermissionEnum::VacationRequest,
                ],
                [
                    PermissionEnum::TimeEntryApprove,
                    PermissionEnum::UserViewAny,
                    PermissionEnum::DutyPlanPublish,
                    PermissionEnum::InvoiceView,
                ],
            ],
            'support' => [
                UserRole::Support->value,
                [
                    PermissionEnum::OrganizationView,
                    PermissionEnum::CustomerViewAny,
                    PermissionEnum::InvoiceViewAny,
                    PermissionEnum::AuditLogView,
                    PermissionEnum::AccessAuditView,
                ],
                // Support darf strikt nichts schreiben.
                [
                    PermissionEnum::CustomerCreate,
                    PermissionEnum::CustomerUpdate,
                    PermissionEnum::CustomerDelete,
                    PermissionEnum::InvoiceCreate,
                    PermissionEnum::InvoiceIssue,
                    PermissionEnum::DiaryCreate,
                    PermissionEnum::TimeEntryCreate,
                    PermissionEnum::ReportExport,
                ],
            ],
        ];
    }

    /**
     * @param  list<PermissionEnum>  $allowed
     * @param  list<PermissionEnum>  $denied
     */
    #[DataProvider('roleProfileProvider')]
    public function test_role_profile_permission_matrix(string $role, array $allowed, array $denied): void {
        $user = User::factory()
            ->state(['organization_id' => $this->organization->id])
            ->create();

        // Spatie braucht den Org-Kontext beim Zuweisen UND beim Auswerten.
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);
        // Wichtig: bei mehreren Rollen-Instanzen pro Name (eine global,
        // eine team-scoped) muss die team-scoped Role-Instanz explizit
        // übergeben werden, sonst greift Spatie auf die globale zurück.
        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();
        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        foreach ($allowed as $permission) {
            $this->assertTrue(
                $user->hasPermissionTo($permission->value),
                "Rolle '{$role}' muss '{$permission->value}' besitzen.",
            );
        }

        foreach ($denied as $permission) {
            $this->assertFalse(
                $user->hasPermissionTo($permission->value),
                "Rolle '{$role}' darf '{$permission->value}' NICHT besitzen.",
            );
        }
    }

    public function test_seeder_is_idempotent(): void {
        $rolesBefore = Role::query()->count();
        $permsBefore = Permission::query()->count();
        $assignmentsBefore = DB::table(config('permission.table_names.role_has_permissions'))->count();

        // Zweiter und dritter Durchlauf dürfen weder Roles noch
        // Permissions noch Zuordnungen vervielfachen.
        $this->seed(PermissionsSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->assertSame($rolesBefore, Role::query()->count(), 'Roles werden dupliziert.');
        $this->assertSame($permsBefore, Permission::query()->count(), 'Permissions werden dupliziert.');
        $this->assertSame(
            $assignmentsBefore,
            DB::table(config('permission.table_names.role_has_permissions'))->count(),
            'Role-Permission-Zuordnungen werden dupliziert.',
        );
    }

    public function test_all_enum_roles_exist_in_database(): void {
        foreach (UserRole::values() as $roleName) {
            $this->assertTrue(
                Role::where('name', $roleName)->exists(),
                "Rolle '{$roleName}' muss vom Seeder global angelegt werden.",
            );
        }
    }
}
