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

    /** MVP-839: Hauptprofil wechseln ist Org-Admin-Sache, Deinstallation Plattform-Sache. */
    public function test_admin_can_set_primary_but_not_uninstall(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($admin)->post(route('admin.branch-profiles.install', 'it'))->assertRedirect();
        $this->actingAs($admin)->post(route('admin.branch-profiles.install', 'shk'))->assertRedirect();
        $this->assertSame('it', Organization::query()->findOrFail($this->organization->id)->primaryBranchProfileCode());

        $this->actingAs($admin)
            ->post(route('admin.branch-profiles.primary', 'shk'))
            ->assertRedirect(route('admin.branch-profiles.index'));
        $this->assertSame('shk', Organization::query()->findOrFail($this->organization->id)->primaryBranchProfileCode());

        // Nicht installiert → 404, Deinstallation ohne Recht → 403.
        $this->actingAs($admin)->post(route('admin.branch-profiles.primary', 'galabau'))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.branch-profiles.uninstall', 'it'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.branch-profiles.index'))
            ->assertOk()
            ->assertSee(__('Hauptprofil'))
            ->assertSee(__('Als Hauptprofil festlegen'))
            ->assertDontSee(__('Deinstallieren'));
    }

    public function test_platform_admin_can_uninstall_a_profile(): void {
        $platformAdmin = User::factory()->admin()->create(['organization_id' => $this->organization->id, 'is_platform_admin' => true]);

        $this->actingAs($platformAdmin)->post(route('admin.branch-profiles.install', 'it'))->assertRedirect();
        $this->actingAs($platformAdmin)->post(route('admin.branch-profiles.install', 'galabau'))->assertRedirect();

        $this->actingAs($platformAdmin)
            ->get(route('admin.branch-profiles.index'))
            ->assertOk()
            ->assertSee(__('Deinstallieren'));

        $this->actingAs($platformAdmin)
            ->post(route('admin.branch-profiles.uninstall', 'galabau'))
            ->assertRedirect(route('admin.branch-profiles.index'));

        $organization = Organization::query()->findOrFail($this->organization->id);
        $this->assertSame(['it'], $organization->installedBranchProfileCodes());
        $this->assertNull(Classification::query()->where('organization_id', $organization->id)
            ->where('domain', 'entry_type')->where('code', 'pflegegang')->first());
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'branch_profile.uninstalled']);

        $this->actingAs($platformAdmin)->post(route('admin.branch-profiles.uninstall', 'galabau'))->assertNotFound();
    }
}
