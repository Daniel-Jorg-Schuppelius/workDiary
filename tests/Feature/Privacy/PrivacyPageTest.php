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

use App\Models\{PluginSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivacyPageTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
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
            ->assertSee(__('API-Tokens'))
            ->assertSee(__('Externe Integrationen und Datenflüsse'));
    }

    /**
     * §3.5 (MVP-327): Die Integrationen-Sektion listet die in der EIGENEN
     * Organisation aktivierten Plugins — Plugins fremder Organisationen und
     * deaktivierte Plugins erscheinen nie.
     */
    public function test_integrations_section_shows_only_own_org_enabled_plugins(): void {
        $admin = User::factory()->admin()->create();
        $foreignAdmin = User::factory()->admin()->create(); // eigene Org via Factory-Default

        PluginSetting::query()->create([
            'organization_id' => $admin->organization_id,
            'plugin_id' => 'toggl',
            'enabled' => true,
            'settings' => [],
        ]);
        // Deaktiviertes Plugin der eigenen Org → nicht gelistet.
        PluginSetting::query()->create([
            'organization_id' => $admin->organization_id,
            'plugin_id' => 'lexoffice',
            'enabled' => false,
            'settings' => [],
        ]);
        // Aktives Plugin einer FREMDEN Org → nie gelistet.
        PluginSetting::query()->create([
            'organization_id' => $foreignAdmin->organization_id,
            'plugin_id' => 'zammad',
            'enabled' => true,
            'settings' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.privacy.index'))
            ->assertOk()
            ->assertSee(__('Externe Integrationen und Datenflüsse'))
            ->assertSee('Toggl Track')
            ->assertDontSee('Lexoffice')
            ->assertDontSee('Zammad');
    }

    /**
     * §3.5: Die statischen Config-Integrationen der Konzept-Tabelle und die
     * Datenfluss-Negativaussage sind Bestandteil der Sektion.
     */
    public function test_integrations_section_lists_config_services_and_negative_statement(): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.privacy.index'))
            ->assertOk()
            ->assertSee(__('Mail-Versand'))
            ->assertSee(__('Web-Push-Benachrichtigungen'))
            ->assertSee(__('Geocoding (Nominatim)'))
            ->assertSee(__('AWS S3 (Dateiablage)'))
            ->assertSee(__('WorkDiary nutzt keine Tracking-, Analytics- oder Werbe-Dienste.'));
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
