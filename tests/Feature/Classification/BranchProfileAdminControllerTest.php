<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BranchProfileAdminControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Classification;

use App\Enums\User\UserRole;
use App\Models\{AuditLog, Classification, ClassificationRequirement, Organization, Tag, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class BranchProfileAdminControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();

        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
    }

    public function test_user_without_branch_profile_permissions_cannot_view_catalog(): void {
        $user = $this->userWithRole(UserRole::User->value);

        $this->actingAs($user)
            ->get(route('admin.branch-profiles.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_branch_profile_catalog(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index'))
            ->assertOk()
            ->assertSee('Branchenprofile')
            ->assertSee('IT-Service / Managed Services')
            ->assertSee('Handwerk / Service allgemein')
            ->assertSee('Elektro')
            ->assertSee('SHK')
            ->assertSee('Spedition und Transportlogistik')
            ->assertSee('Steuerberatung')
            ->assertSee('Veranstaltungstechnik');
    }

    public function test_catalog_shows_package_content_preview(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index', ['q' => 'elektro']))
            ->assertOk()
            ->assertSee('Auftragsarten')
            ->assertSee('Checklisten')
            ->assertSee('Raumanforderungen')
            // Inhaltsvorschau: konkrete Auftragsart und Checkliste des Pakets.
            ->assertSee('Installation')
            ->assertSee('Sicherheitscheck Elektro (5 Sicherheitsregeln)');
    }

    public function test_admin_can_filter_branch_profile_catalog_by_search_query(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index', ['q' => 'elektro']))
            ->assertOk()
            ->assertSee('Elektro')
            ->assertDontSee('Steuerberatung');
    }

    public function test_admin_can_filter_branch_profile_catalog_by_installed_state(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.branch-profiles.install', 'it'))
            ->assertRedirect(route('admin.branch-profiles.index'));

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index', ['installed' => 'installed']))
            ->assertOk()
            ->assertSee('IT-Service / Managed Services')
            ->assertDontSee('Elektro');

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index', ['installed' => 'not_installed']))
            ->assertOk()
            ->assertSee('Elektro')
            ->assertDontSee('IT-Service / Managed Services');
    }

    public function test_admin_can_install_branch_profile(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.branch-profiles.install', 'it'))
            ->assertRedirect(route('admin.branch-profiles.index'));

        $this->assertGreaterThan(0, Classification::query()->where('organization_id', $this->organization->id)->count());
        $this->assertGreaterThan(0, ClassificationRequirement::query()->where('organization_id', $this->organization->id)->count());
        $this->assertGreaterThan(0, Tag::query()->count());
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'branch_profile.installed',
        ]);

        $organization = Organization::query()->findOrFail($this->organization->id);
        $this->assertSame('it', $organization->settings['branch_profile_code'] ?? null);
    }

    public function test_force_install_reapplies_profile_updates(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)
            ->post(route('admin.branch-profiles.install', 'it'))
            ->assertRedirect(route('admin.branch-profiles.index'));

        $classification = Classification::query()
            ->where('organization_id', $this->organization->id)
            ->where('domain', 'entry_type')
            ->where('code', 'incident')
            ->firstOrFail();
        $classification->update(['label' => 'Incident lokal']);

        $this->actingAs($admin)
            ->post(route('admin.branch-profiles.install', 'it'), ['force' => '1'])
            ->assertRedirect(route('admin.branch-profiles.index'));

        $refreshedClassification = $classification->fresh();

        $this->assertInstanceOf(Classification::class, $refreshedClassification);
        $this->assertSame('Incident', $refreshedClassification->label);
        $this->assertSame(
            2,
            AuditLog::query()->where('organization_id', $this->organization->id)->where('event', 'branch_profile.installed')->count(),
        );
    }

    private function userWithRole(string $role): User {
        $user = User::factory()->create(['organization_id' => $this->organization->id]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->organization->id);

        $orgRole = Role::query()
            ->where('name', $role)
            ->where('team_id', $this->organization->id)
            ->firstOrFail();

        $user->syncRoles([$orgRole]);
        $registrar->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        return $user;
    }
}
