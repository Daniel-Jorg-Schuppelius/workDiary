<?php
/*
 * Created on   : Mon Nov 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TeamScopedPermissionRegistrarTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\Platform\{Organization, User};
use App\Support\Permissions\TeamScopedPermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Leistungsanalyse 2026-11-02, perf-14: Der Rechte-Cache gilt je Team; andere
 * Mandanten werden weder geladen noch hydriert, ein Teamwechsel wechselt den
 * Schlüssel, eine Rechteänderung entwertet alle Schlüssel.
 */
final class TeamScopedPermissionRegistrarTest extends TestCase {
    use RefreshDatabase;

    public function test_registrar_is_team_scoped_and_hydrates_only_the_current_team(): void {
        $registrar = app(PermissionRegistrar::class);
        $this->assertInstanceOf(TeamScopedPermissionRegistrar::class, $registrar);

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $roleA = Role::query()->where('name', 'admin')->where('team_id', $orgA->id)->firstOrFail();
        $roleB = Role::query()->where('name', 'admin')->where('team_id', $orgB->id)->firstOrFail();
        $permission = $roleA->permissions()->firstOrFail();
        $this->assertTrue($roleB->permissions()->where('permissions.id', $permission->id)->exists(), 'beide Admin-Rollen tragen das Recht');

        $registrar->setPermissionsTeamId($orgA->id);
        $registrar->forgetCachedPermissions();
        $teams = $registrar->getPermissions(['name' => $permission->name])->first()?->roles->pluck('team_id')->filter()->unique()->all();
        $this->assertSame([$orgA->id], array_values($teams ?? []), 'nur Rollen des Teams A und globale Rollen');
        $this->assertStringEndsWith(':t' . $orgA->id, $registrar->scopedCacheKey());

        $registrar->setPermissionsTeamId($orgB->id);
        $teams = $registrar->getPermissions(['name' => $permission->name])->first()?->roles->pluck('team_id')->filter()->unique()->all();
        $this->assertSame([$orgB->id], array_values($teams ?? []), 'Teamwechsel lädt das andere Team');
    }

    public function test_forgetting_bumps_the_version_so_every_team_key_expires(): void {
        $registrar = app(PermissionRegistrar::class);
        $org = Organization::factory()->create();
        $registrar->setPermissionsTeamId($org->id);
        $registrar->getPermissions();
        $key = $registrar->scopedCacheKey();
        $this->assertTrue(Cache::has($key));
        $version = $registrar->version();

        $registrar->forgetCachedPermissions();

        $this->assertSame($version + 1, $registrar->version());
        $this->assertNotSame($key, $registrar->scopedCacheKey(), 'neue Version, neuer Schlüssel');
        $this->assertFalse(Cache::has($key));
    }

    public function test_permission_checks_keep_their_meaning_across_teams(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $adminA = User::factory()->admin()->create(['organization_id' => $orgA->id]);
        $userB = User::factory()->user()->create(['organization_id' => $orgB->id]);
        $registrar = app(PermissionRegistrar::class);

        $registrar->setPermissionsTeamId($orgA->id);
        $this->assertTrue($adminA->fresh()->can('access.manage'));

        $registrar->setPermissionsTeamId($orgB->id);
        $this->assertFalse($userB->fresh()->can('access.manage'));
        $this->assertFalse($adminA->fresh()->can('access.manage'), 'Rolle aus Team A zählt in Team B nicht');
    }

    public function test_a_request_does_not_rewrite_the_permission_cache(): void {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $registrar = app(PermissionRegistrar::class);

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $version = $registrar->version();
        $key = $registrar->scopedCacheKey();
        $this->assertTrue(Cache::has($key), 'Cache des Teams liegt nach dem Request vor');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertSame($version, $registrar->version(), 'ein Request entwertet den Cache nicht');
        $this->assertTrue(Cache::has($key));
    }
}
