<?php
/*
 * Created on   : Mon Jun 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleConfigurationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Models\{LicenseFlagOverride, Organization, User};
use App\Services\Licensing\FeatureFlagResolver;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Org-bezogene Modulkonfiguration (MVP-052): Aktivieren/Deaktivieren über die
 * Admin-Oberfläche, Manipulationsschutz, Org-Scope, serverseitige Sperre mit
 * unterscheidbarer Meldung und Ausblendung in der globalen Suche.
 */
class ModuleConfigurationTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    private function admin(): User {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        app()->instance('currentOrganization', $org);

        return $admin;
    }

    public function test_org_admin_can_disable_a_licensed_module(): void {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.modules.disable'), ['module' => 'module.documents', 'reason' => 'nicht benötigt'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('license_flag_overrides', [
            'organization_id' => $admin->organization_id,
            'flag' => 'module.documents',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'license.moduleDisabled']);

        app(FeatureFlagResolver::class)->flush();
        $this->assertFalse(app(FeatureFlagResolver::class)->isEnabled('module.documents'));
    }

    public function test_org_admin_can_reenable_a_disabled_module(): void {
        $admin = $this->admin();
        LicenseFlagOverride::query()->create([
            'organization_id' => $admin->organization_id,
            'flag' => 'module.documents',
            'disabled_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.modules.enable'), ['module' => 'module.documents'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('license_flag_overrides', [
            'organization_id' => $admin->organization_id,
            'flag' => 'module.documents',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'license.moduleEnabled']);
    }

    public function test_unlicensed_module_cannot_be_disabled_via_manipulation(): void {
        // Free-Org: kein Modul lizenziert.
        $org = Organization::factory()->free()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        app()->instance('currentOrganization', $org);

        $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.modules.disable'), ['module' => 'module.documents'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('license_flag_overrides', [
            'organization_id' => $org->id,
            'flag' => 'module.documents',
        ]);
    }

    public function test_unknown_module_code_is_rejected(): void {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.modules.disable'), ['module' => 'module.does_not_exist'])
            ->assertSessionHasErrors('module');
    }

    public function test_disable_is_scoped_to_own_organization(): void {
        $admin = $this->admin();
        $otherOrg = Organization::factory()->enterprise()->create();

        $this->actingAs($admin)
            ->post(route('admin.license.modules.disable'), ['module' => 'module.documents']);

        $this->assertDatabaseMissing('license_flag_overrides', [
            'organization_id' => $otherOrg->id,
            'flag' => 'module.documents',
        ]);
    }

    public function test_direct_call_to_disabled_module_is_blocked_with_distinct_message(): void {
        $admin = $this->admin();
        LicenseFlagOverride::query()->create([
            'organization_id' => $admin->organization_id,
            'flag' => 'module.documents',
            'disabled_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('documents.index'));
        $response->assertStatus(423);
        $this->assertStringContainsString('deaktiviert', (string) $response->exception?->getMessage());
    }

    public function test_disabled_module_is_hidden_from_global_search(): void {
        $admin = $this->admin();
        \App\Models\Document::factory()->create([
            'title' => 'Zuluwort Wartungsvertrag',
            'created_by_user_id' => $admin->id,
        ]);

        // Vor der Deaktivierung erscheint die Dokumente-Gruppe …
        $before = $this->actingAs($admin)->getJson(route('api.internal.search', ['q' => 'zuluwort']));
        $this->assertNotNull(collect($before->json('groups'))->firstWhere('key', 'documents'));

        LicenseFlagOverride::query()->create([
            'organization_id' => $admin->organization_id,
            'flag' => 'module.documents',
            'disabled_at' => now(),
        ]);
        app(FeatureFlagResolver::class)->flush();

        // … danach nicht mehr.
        $after = $this->actingAs($admin)->getJson(route('api.internal.search', ['q' => 'zuluwort']));
        $this->assertNull(collect($after->json('groups'))->firstWhere('key', 'documents'));
    }
}
