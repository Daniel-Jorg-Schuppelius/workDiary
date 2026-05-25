<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PrivacyPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Privacy;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
    }

    public function test_index_requires_authentication(): void {
        $this->get(route('admin.privacy.index'))->assertRedirect(route('login'));
    }

    public function test_index_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->get(route('admin.privacy.index'))
            ->assertForbidden();
    }

    public function test_index_renders_for_org_admin_with_required_sections(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.privacy.index'))
            ->assertOk()
            ->assertSee(__('Datenschutz'))
            ->assertSee(__('Status auf einen Blick'))
            ->assertSee(__('Datenkategorien und Aufbewahrung'))
            ->assertSee(__('Aktive Sessions'))
            ->assertSee(__('API-Tokens'));
    }

    public function test_geschaeftsfuehrung_role_has_privacy_view_permissions_but_not_revoke(): void {
        // Direkter Permission-Matrix-Check statt HTTP-Render: Tests mit
        // nicht-Admin-Rollen treffen unter Spatie-team-scoped Setup das
        // gleiche bekannte Test-Infrastructure-Gap wie der vorhandene
        // skipped DashboardTest::test_dashboard_shows_team_section_for_admin.
        // hasPermissionTo() liefert in der Test-Pipeline trotz korrekter
        // role_has_permissions-Einträge false; im echten Request-Lifecycle
        // setzt SetOrganizationContext den Spatie-Team-Kontext korrekt.
        $gf = User::factory()->geschaeftsfuehrung()->create();

        $role = \Spatie\Permission\Models\Role::query()
            ->where('name', 'geschaeftsfuehrung')
            ->where('team_id', $gf->organization_id)
            ->firstOrFail();
        $perms = $role->permissions()->pluck('name')->all();

        $this->assertContains(\App\Enums\User\Permission::PrivacyView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacySessionsView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacyTokensView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacyReportExport->value, $perms);
        // Geschäftsführung darf NICHT widerrufen (read-only laut Spec §2.2).
        $this->assertNotContains(\App\Enums\User\Permission::PrivacySessionsRevoke->value, $perms);
        $this->assertNotContains(\App\Enums\User\Permission::PrivacyTokensRevoke->value, $perms);
    }

    public function test_support_role_has_privacy_view_permissions_but_not_revoke(): void {
        $support = User::factory()->support()->create();

        $role = \Spatie\Permission\Models\Role::query()
            ->where('name', 'support')
            ->where('team_id', $support->organization_id)
            ->firstOrFail();
        $perms = $role->permissions()->pluck('name')->all();

        $this->assertContains(\App\Enums\User\Permission::PrivacyView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacySessionsView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacyTokensView->value, $perms);
        $this->assertNotContains(\App\Enums\User\Permission::PrivacySessionsRevoke->value, $perms);
        $this->assertNotContains(\App\Enums\User\Permission::PrivacyTokensRevoke->value, $perms);
    }

    public function test_admin_role_has_all_privacy_permissions_including_revoke(): void {
        $admin = User::factory()->admin()->create();

        $role = \Spatie\Permission\Models\Role::query()
            ->where('name', 'admin')
            ->where('team_id', $admin->organization_id)
            ->firstOrFail();
        $perms = $role->permissions()->pluck('name')->all();

        $this->assertContains(\App\Enums\User\Permission::PrivacyView->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacySessionsRevoke->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacyTokensRevoke->value, $perms);
        $this->assertContains(\App\Enums\User\Permission::PrivacyReportExport->value, $perms);
    }
}
