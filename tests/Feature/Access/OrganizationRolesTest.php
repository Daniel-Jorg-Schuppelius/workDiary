<?php
/*
 * Created on   : Wed Oct 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrganizationRolesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Enums\User\UserRole;
use App\Models\Platform\{Organization, User};
use App\Services\Org\OrganizationRoles;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Rollen nach Namen kommen aus der Organisation, nie aus der globalen Zeile
 * gleichen Namens (Vollscan 2026-10-21, Nebenbefund zu lk-06).
 */
class OrganizationRolesTest extends TestCase {
    use RefreshDatabase;

    public function test_admin_role_is_the_organizations_row_although_a_global_one_exists(): void {
        $this->seed(PermissionsSeeder::class);
        $org = Organization::factory()->create();

        $this->assertTrue(Role::query()->whereNull('team_id')->where('name', UserRole::Admin->value)->exists());
        $this->assertSame($org->id, (int) OrganizationRoles::named(UserRole::Admin->value, $org->id)->team_id);
    }

    public function test_missing_role_is_created_inside_the_organization(): void {
        $org = Organization::factory()->create();

        $role = OrganizationRoles::named('sso-import', $org->id);

        $this->assertSame($org->id, (int) $role->team_id);
        $this->assertFalse(Role::query()->whereNull('team_id')->where('name', 'sso-import')->exists());
        $this->assertSame($role->id, OrganizationRoles::named('sso-import', $org->id)->id);
    }

    public function test_role_filter_finds_admins_on_either_row(): void {
        $this->seed(PermissionsSeeder::class);
        $org = Organization::factory()->create();
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        $onOrgRow = User::factory()->create(['organization_id' => $org->id]);
        $onOrgRow->assignRole(OrganizationRoles::named(UserRole::Admin->value, $org->id));
        $onGlobalRow = User::factory()->create(['organization_id' => $org->id]);
        $onGlobalRow->assignRole(Role::query()->whereNull('team_id')->where('name', UserRole::Admin->value)->firstOrFail());
        User::factory()->user()->create(['organization_id' => $org->id]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);

        $ids = User::query()
            ->role(OrganizationRoles::matching([UserRole::Admin->value], $org->id))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$onOrgRow->id, $onGlobalRow->id], $ids);
    }

    public function test_tenant_registration_assigns_the_admin_role_of_the_new_organization(): void {
        $this->seed(PermissionsSeeder::class);
        config(['app.registration_enabled' => true]);

        $this->post(route('register'), [
            'org_name' => 'Rollen GmbH',
            'name' => 'Rita Rolle',
            'email' => 'rita@rollen.test',
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ])->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'rita@rollen.test')->firstOrFail();
        $teams = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)
            ->pluck('roles.team_id')
            ->map(static fn ($id): ?int => $id === null ? null : (int) $id)
            ->all();

        $this->assertSame([$user->organization_id], $teams);
    }
}
