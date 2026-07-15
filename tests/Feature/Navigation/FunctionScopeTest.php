<?php
/*
 * Created on   : Wed Jul 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FunctionScopeTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Navigation;

use App\Enums\Licensing\ModuleStatus;
use App\Enums\User\Permission;
use App\Models\{LicenseFlagOverride, Organization, User};
use App\Services\Licensing\{ModuleScopeService, ModuleStatusResolver};
use App\Services\Navigation\NavigationRegistry;
use App\Services\Onboarding\OnboardingChecklistResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 081 (MVP-372–376): Funktionsumfang-Presets, Menüpersonalisierung,
 * Funktionskatalog und die Sichtbarkeits-Leitplanke D13 (Ausblenden ist nie
 * Zugriffsschutz).
 */
class FunctionScopeTest extends TestCase {
    use RefreshDatabase;

    private function admin(Organization $org): User {
        return User::factory()->admin()->create(['organization_id' => $org->id]);
    }

    // ── MVP-373: Presets / ModuleScopeService ────────────────────────────

    public function test_preset_deactivates_non_selected_modules_without_data_loss(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        $result = app(ModuleScopeService::class)->applyPreset($org, 'schlank', $admin);

        // „Schlanker Start“ hat keine Zusatzmodule → alle vorher aktiven werden deaktiviert.
        $this->assertNotEmpty($result['disabled']);
        $this->assertContains('module.planung', $result['disabled']);

        // Nur Disable-Overrides, keine Datenlöschung: LicenseFlagOverride-Zeilen existieren.
        $this->assertDatabaseHas('license_flag_overrides', [
            'organization_id' => $org->id,
            'flag' => 'module.planung',
        ]);

        $status = app(ModuleStatusResolver::class)->statusFor($org, 'module.planung');
        $this->assertSame(ModuleStatus::InactiveByCustomer, $status);
    }

    public function test_preset_never_enables_unlicensed_modules(): void {
        $org = Organization::factory()->free()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        // „Voller Umfang“ auf free: keine lizenzierten Module → nichts zu aktivieren.
        $result = app(ModuleScopeService::class)->applyPreset($org, 'voll', $admin);

        $this->assertSame([], $result['enabled']);
        $this->assertSame(ModuleStatus::NotLicensed, app(ModuleStatusResolver::class)->statusFor($org, 'module.fuhrpark'));
        $this->assertDatabaseMissing('license_flag_overrides', [
            'organization_id' => $org->id,
            'flag' => 'module.fuhrpark',
        ]);
    }

    public function test_preset_is_idempotent_and_reactivation_restores(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $service = app(ModuleScopeService::class);

        $service->applyPreset($org, 'schlank', $admin);
        // Zweiter identischer Lauf ändert nichts mehr.
        $second = $service->applyPreset($org, 'schlank', $admin);
        $this->assertSame([], $second['disabled']);

        // Zurück auf Vollumfang reaktiviert die deaktivierten Module.
        $back = $service->applyPreset($org, 'voll', $admin);
        $this->assertContains('module.planung', $back['enabled']);
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($org, 'module.planung'));
    }

    public function test_scope_page_requires_permission(): void {
        $org = Organization::factory()->enterprise()->create();
        $member = User::factory()->user()->create(['organization_id' => $org->id]);

        $this->actingAs($member)->get(route('admin.scope.index'))->assertForbidden();
    }

    public function test_admin_can_open_and_save_scope_page(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);

        $this->actingAs($admin)->get(route('admin.scope.index'))->assertOk();

        $this->actingAs($admin)
            ->post(route('admin.scope.save'), ['modules' => ['module.vertrieb']])
            ->assertRedirect();

        // Alles außer module.vertrieb deaktiviert; scope_configured_at gesetzt.
        $org->refresh();
        $this->assertNotNull(($org->settings ?? [])['scope_configured_at'] ?? null);
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($org, 'module.vertrieb'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, app(ModuleStatusResolver::class)->statusFor($org, 'module.planung'));
    }

    public function test_scope_manage_permission_cannot_reach_license_admin(): void {
        // Recht organization.scope.manage allein öffnet NICHT die Lizenzverwaltung.
        $org = Organization::factory()->enterprise()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->givePermissionTo(Permission::OrganizationScopeManage->value);

        $this->actingAs($user)->get(route('admin.scope.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.license.index'))->assertForbidden();
    }

    // ── MVP-374: Per-User-Menüanpassung ──────────────────────────────────

    public function test_user_can_hide_and_unhide_nav_sections(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);

        $this->actingAs($admin)
            ->post(route('me.navigation.customize.save'), [
                'hidden' => [NavigationRegistry::KEY_SECTION . 'fleet'],
            ])
            ->assertRedirect();

        $admin->refresh();
        $this->assertSame([NavigationRegistry::KEY_SECTION . 'fleet'], $admin->getPreference(NavigationRegistry::PREFERENCE_HIDDEN));

        // Sidebar zeigt die Fuhrpark-Sektion nicht mehr, aber die Route bleibt erreichbar.
        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();
        $aside = substr($html, strpos($html, '<aside id="app-sidebar"'));
        $this->assertStringNotContainsString('data-sidebar-section-key="fleet"', $aside);

        // Wieder einblenden.
        $this->actingAs($admin)
            ->post(route('me.navigation.unhide'), ['key' => NavigationRegistry::KEY_SECTION . 'fleet'])
            ->assertRedirect();
        $admin->refresh();
        $this->assertSame([], $admin->getPreference(NavigationRegistry::PREFERENCE_HIDDEN));
    }

    public function test_hidden_nav_never_blocks_route_access(): void {
        // D13: Ausblenden ist rein kosmetisch — die Route bleibt erlaubt.
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        $admin->setPreference(NavigationRegistry::PREFERENCE_HIDDEN, [NavigationRegistry::KEY_ITEM . 'vehicles.index']);

        $this->actingAs($admin)->get(route('vehicles.index'))->assertOk();
    }

    public function test_unknown_hidden_keys_are_rejected(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);

        $this->actingAs($admin)
            ->post(route('me.navigation.customize.save'), [
                'hidden' => ['item:this.route.does.not.exist', 'garbage'],
            ])
            ->assertRedirect();

        $admin->refresh();
        // Nur registrierte Schlüssel überleben die Whitelist.
        $this->assertSame([], $admin->getPreference(NavigationRegistry::PREFERENCE_HIDDEN));
    }

    // ── MVP-375: Funktionskatalog ────────────────────────────────────────

    public function test_function_catalog_lists_states(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        // module.fuhrpark org-deaktivieren.
        LicenseFlagOverride::query()->create([
            'organization_id' => $org->id,
            'flag' => 'module.fuhrpark',
            'disabled_at' => now(),
            'disabled_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('me.functions'))
            ->assertOk()
            ->assertSee(__('scope.functions.state.org_disabled'));
    }

    // ── MVP-373: Onboarding-Schritt org.scope ────────────────────────────

    public function test_onboarding_scope_step_completes_after_configuration(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        $before = collect(app(OnboardingChecklistResolver::class)->forOrganization($org)['steps'])
            ->firstWhere('code', 'org.scope');
        $this->assertNotNull($before);
        $this->assertFalse($before['done']);

        app(ModuleScopeService::class)->applyPreset($org, 'schlank', $admin);

        $after = collect(app(OnboardingChecklistResolver::class)->forOrganization($org)['steps'])
            ->firstWhere('code', 'org.scope');
        $this->assertTrue($after['done']);
    }

    public function test_scope_audit_is_written(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        app(ModuleScopeService::class)->applyPreset($org, 'service_handwerk', $admin);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'license.scopeConfigured',
        ]);
    }
}
