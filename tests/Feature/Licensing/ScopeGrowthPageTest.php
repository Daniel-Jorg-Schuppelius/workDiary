<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeGrowthPageTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Enums\User\UserRole;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\{ModuleScopeService, ModuleStatusResolver};
use App\Services\Navigation\{NavFocusService, NavigationRegistry, StartPageResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erweiterung mit Vorschau (MVP-1195) und freiwilliger Bestandswechsel mit
 * konfliktbewusster Rücknahme (MVP-1196), Feature 165.
 */
class ScopeGrowthPageTest extends TestCase {
    use RefreshDatabase;

    private function admin(Organization $org): User {
        return User::factory()->admin()->create(['organization_id' => $org->id]);
    }

    private function scope(): ModuleScopeService {
        return app(ModuleScopeService::class);
    }

    private function moduleStatus(Organization $org, string $code): ModuleStatus {
        return app(ModuleStatusResolver::class)->statusFor($org->fresh() ?? $org, $code);
    }

    public function test_growth_page_shows_states_and_packages(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $overview = collect($this->scope()->growthOverview($org->fresh()))->keyBy('code');
        $this->assertSame('active', $overview['module.vertrieb']['state']);
        $this->assertSame('disabled', $overview['module.planung']['state']);
        $this->assertSame('growth', $overview['module.finance']['state']);
        $this->assertContains('Team', $overview['module.planung']['packages']);
        $this->assertContains('compact_team', $overview['module.planung']['workspaces']);
        $this->assertSame(['module.helpdesk'], $overview['module.service_desk']['requires']);
        $this->assertSame(['module.helpdesk'], $overview['module.service_desk']['requires_missing']);

        $this->actingAs($admin)->get(route('admin.scope.growth'))
            ->assertOk()
            ->assertSee(__('scope.growth.state.growth'))
            ->assertSee(__('scope.growth.request_heading'))
            ->assertSee((string) $org->license_uid);

        $free = Organization::factory()->free()->create();
        $freeAdmin = $this->admin($free);
        app()->instance('currentOrganization', $free);
        $freeOverview = collect($this->scope()->growthOverview($free))->keyBy('code');
        $this->assertSame('not_licensed', $freeOverview['module.vertrieb']['state']);
        $this->assertSame(['Start', 'Team', 'Betrieb'], $freeOverview['module.vertrieb']['packages']);
        $this->actingAs($freeAdmin)->get(route('admin.scope.growth'))->assertOk()->assertSee(__('scope.growth.in_packages', ['packages' => 'Start, Team, Betrieb']));
    }

    public function test_activation_enables_module_and_offers_its_workspace(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $this->assertNotContains('compact_team', (array) ((array) $org->fresh()->settings)[NavFocusService::SETTING_AVAILABLE]);

        $this->actingAs($admin)->from(route('admin.scope.growth'))
            ->post(route('admin.scope.growth.activate'), ['module' => 'module.planung'])
            ->assertRedirect(route('admin.scope.growth'))
            ->assertSessionHas('success');

        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertContains('module.planung', $this->scope()->profile($org->fresh())['chosen']);
        $available = (array) ((array) $org->fresh()->settings)[NavFocusService::SETTING_AVAILABLE];
        $this->assertContains('compact_team', $available);
        $this->assertContains('compact_day', $available);
        $this->assertSame('all', end($available));

        // Nicht lizenziertes Modul lässt sich hier nicht aktivieren.
        $this->actingAs($admin->fresh())->from(route('admin.scope.growth'))
            ->post(route('admin.scope.growth.activate'), ['module' => 'module.finance'])
            ->assertSessionHas('error');
        $this->assertSame(ModuleStatus::NotLicensed, $this->moduleStatus($org, 'module.finance'));
    }

    public function test_revert_restores_the_state_before_the_bundle(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->assertFalse($this->scope()->isLimited($org));

        $this->scope()->applyPreset($org, 'start', $admin);
        $this->assertTrue($this->scope()->isLimited($org->fresh()));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.planung'));

        $this->actingAs($admin)->get(route('admin.scope.revert'))->assertOk()->assertSee(__('scope.revert.changes_heading'));
        $this->actingAs($admin)->post(route('admin.scope.revert.apply'))->assertRedirect(route('admin.scope.index'));

        $org->refresh();
        $this->assertFalse($this->scope()->isLimited($org));
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertNull(((array) $org->settings)[NavFocusService::SETTING_AVAILABLE] ?? null);
        $this->assertNull(data_get((array) $org->settings, StartPageResolver::settingKey(UserRole::User)));
        $this->assertSame([], $this->scope()->profile($org)['snapshot']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event' => 'license.scopeReverted']);
        // Ohne Schnappschuss gibt es keine Rücknahme mehr (frische Person: die
        // Organisation hängt sonst gecached am Modell des vorigen Requests).
        $this->actingAs($admin->fresh())->get(route('admin.scope.revert'))->assertNotFound();
    }

    public function test_revert_keeps_manual_changes_unless_overridden(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);

        // Manuelle Änderungen nach der Übernahme: Modul aktiviert, Standardbereich geändert.
        $this->scope()->enableModule($org->fresh(), 'module.planung', $admin);
        $focus = app(NavFocusService::class);
        $settings = (array) $org->fresh()->settings;
        $focus->saveCuration($org->fresh(), (array) $settings[NavFocusService::SETTING_AVAILABLE], 'compact_orders', (array) $settings[NavFocusService::SETTING_ROLES], []);

        $preview = $this->scope()->revertPreview($org->fresh());
        $this->assertEqualsCanonicalizing(['modules', 'default'], $preview['conflicts']);

        $this->actingAs($admin)->post(route('admin.scope.revert.apply'))->assertRedirect();
        $org->refresh();
        // Konfliktdimensionen bleiben: Module wie manuell gesetzt, Standard bleibt.
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.fuhrpark'));
        $this->assertSame('compact_orders', ((array) $org->settings)[NavFocusService::SETTING_DEFAULT]);
        // Nicht strittige Dimensionen wurden zurückgenommen.
        $this->assertNull(((array) $org->settings)[NavFocusService::SETTING_AVAILABLE] ?? null);
        $this->assertFalse($this->scope()->isLimited($org));
    }

    public function test_revert_with_override_restores_everything(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $this->scope()->enableModule($org->fresh(), 'module.planung', $admin);

        $this->actingAs($admin)->post(route('admin.scope.revert.apply'), ['override_conflicts' => 1])->assertRedirect();

        $org->refresh();
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.fuhrpark'));
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertDatabaseMissing('license_flag_overrides', ['organization_id' => $org->id]);
    }

    public function test_revert_never_restores_a_lost_license(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $org->fresh()->update(['plan' => Organization::PLAN_FREE]);

        $this->scope()->revert($org->fresh(), $admin, true);

        $this->assertSame(ModuleStatus::NotLicensed, $this->moduleStatus($org, 'module.vertrieb'));
        $this->assertSame(ModuleStatus::NotLicensed, $this->moduleStatus($org, 'module.planung'));
    }

    public function test_tenants_stay_separated(): void {
        $a = Organization::factory()->enterprise()->create();
        $b = Organization::factory()->enterprise()->create();
        $adminA = $this->admin($a);
        $userB = User::factory()->user()->create(['organization_id' => $b->id]);
        $userB->setPreference(NavigationRegistry::PREFERENCE_HIDDEN, ['section:sales']);
        app()->instance('currentOrganization', $a);

        $this->scope()->applyPreset($a, 'start', $adminA);

        app()->instance('currentOrganization', $b);
        $this->assertFalse($this->scope()->isLimited($b->fresh()));
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($b, 'module.planung'));
        $this->assertArrayNotHasKey(NavFocusService::SETTING_AVAILABLE, (array) $b->fresh()->settings);
        $this->assertSame(['section:sales'], $userB->fresh()->getPreference(NavigationRegistry::PREFERENCE_HIDDEN));
    }
}
