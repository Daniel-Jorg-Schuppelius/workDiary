<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeSetupTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Enums\User\UserRole;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\{ModuleScopeService, ModuleStatusResolver};
use App\Services\Navigation\{NavFocusService, StartPageResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Schlanke Einrichtung für neue Organisationen (Feature 165, MVP-1189/1190).
 */
class ScopeSetupTest extends TestCase {
    use RefreshDatabase;

    private function scope(): ModuleScopeService {
        return app(ModuleScopeService::class);
    }

    public function test_registration_applies_the_start_defaults_in_limited_mode(): void {
        config(['app.registration_enabled' => true]);

        $this->post(route('register'), [
            'org_name' => 'Kleinbetrieb GmbH',
            'name' => 'Erika Muster',
            'email' => 'erika@kleinbetrieb.test',
            'password' => 'Password123!Strong',
            'password_confirmation' => 'Password123!Strong',
        ])->assertRedirect(route('dashboard'));

        $org = Organization::query()->where('name', 'Kleinbetrieb GmbH')->firstOrFail();
        $profile = $this->scope()->profile($org);
        $this->assertSame(ModuleScopeService::MODE_LIMITED, $profile['mode']);
        $this->assertSame('package', $profile['source']);
        $this->assertSame('start', $profile['key']);
        $this->assertSame(['module.vertrieb'], $profile['chosen']);
        $this->assertNotEmpty($profile['snapshot']);

        $settings = (array) $org->settings;
        $this->assertSame(['compact_day', 'compact_orders', 'all'], $settings[NavFocusService::SETTING_AVAILABLE]);
        $this->assertSame('compact_day', $settings[NavFocusService::SETTING_DEFAULT]);
        $this->assertSame('compact_day', $settings[NavFocusService::SETTING_ROLES]['user']);
        $this->assertSame('compact_orders', $settings[NavFocusService::SETTING_ROLES]['admin']);
        $this->assertSame('today.show', data_get($settings, StartPageResolver::settingKey(UserRole::User)));
        $this->assertSame('duties.index', data_get($settings, StartPageResolver::settingKey(UserRole::Admin)));
        $this->assertNotEmpty($settings['scope_configured_at'] ?? null);
        // Ohne Lizenz ist nichts lizenziert — kein Override, kein Freischalten.
        $this->assertDatabaseMissing('license_flag_overrides', ['organization_id' => $org->id]);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event' => 'license.scopeConfigured']);
    }

    public function test_existing_organizations_stay_untouched(): void {
        $org = Organization::factory()->enterprise()->create();

        $this->assertFalse($this->scope()->isLimited($org));
        $this->assertArrayNotHasKey(NavFocusService::SETTING_AVAILABLE, (array) $org->settings);
    }

    public function test_setup_dialog_lists_goals_and_applies_the_bundle(): void {
        $org = Organization::factory()->pro()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        app()->instance('currentOrganization', $org);

        $this->actingAs($admin)->get(route('admin.scope.setup'))
            ->assertOk()
            ->assertSee(__('scope.setup.goal.orders'))
            ->assertSee(__('scope.setup.goal.billing'));

        $this->actingAs($admin)
            ->post(route('admin.scope.setup.apply'), ['goals' => ['orders', 'time', 'billing']])
            ->assertRedirect(route('admin.scope.index'));

        $org->refresh();
        $settings = (array) $org->settings;
        $this->assertSame(['compact_day', 'compact_orders', 'compact_billing', 'all'], $settings[NavFocusService::SETTING_AVAILABLE]);
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($org, 'module.vertrieb'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, app(ModuleStatusResolver::class)->statusFor($org, 'module.planung'));
        $this->assertTrue($this->scope()->isLimited($org));
    }

    public function test_team_goal_needs_a_license_for_planning(): void {
        $free = Organization::factory()->free()->create();
        $freeAdmin = User::factory()->admin()->create(['organization_id' => $free->id]);
        app()->instance('currentOrganization', $free);

        $this->actingAs($freeAdmin)->post(route('admin.scope.setup.apply'), ['goals' => ['orders', 'time', 'team']])->assertRedirect();
        $this->assertNotContains('compact_team', (array) ((array) $free->fresh()->settings)[NavFocusService::SETTING_AVAILABLE]);

        $pro = Organization::factory()->pro()->create();
        $proAdmin = User::factory()->admin()->create(['organization_id' => $pro->id]);
        app()->instance('currentOrganization', $pro);

        $this->actingAs($proAdmin)->post(route('admin.scope.setup.apply'), ['goals' => ['orders', 'time', 'team']])->assertRedirect();
        $pro->refresh();
        $settings = (array) $pro->settings;
        $this->assertContains('compact_team', $settings[NavFocusService::SETTING_AVAILABLE]);
        $this->assertSame('compact_team', $settings[NavFocusService::SETTING_ROLES]['teamleitung']);
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($pro, 'module.planung'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, app(ModuleStatusResolver::class)->statusFor($pro, 'module.fuhrpark'));
    }

    public function test_unknown_goal_is_rejected(): void {
        $org = Organization::factory()->pro()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->from(route('admin.scope.index'))
            ->post(route('admin.scope.setup.apply'), ['goals' => ['weltherrschaft']])
            ->assertSessionHasErrors('goals.0');
    }

    public function test_platform_org_creation_with_package_preset_applies_the_bundle(): void {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)->post(route('admin.organizations.store'), [
            'name' => 'Neue Firma',
            'plan' => Organization::PLAN_ENTERPRISE,
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
            'is_active' => 1,
            'scope_preset' => 'team',
        ])->assertRedirect();

        $org = Organization::query()->where('name', 'Neue Firma')->firstOrFail();
        $profile = $this->scope()->profile($org);
        $this->assertSame('team', $profile['key']);
        $this->assertSame(ModuleScopeService::MODE_LIMITED, $profile['mode']);
        $this->assertContains('compact_team', (array) ((array) $org->settings)[NavFocusService::SETTING_AVAILABLE]);
        $this->assertSame(ModuleStatus::Active, app(ModuleStatusResolver::class)->statusFor($org, 'module.planung'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, app(ModuleStatusResolver::class)->statusFor($org, 'module.finance'));
    }

    public function test_role_suggestion_is_only_a_suggestion(): void {
        $org = Organization::factory()->pro()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $worker = User::factory()->user()->create(['organization_id' => $org->id]);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $org->refresh();

        $focus = app(NavFocusService::class);
        $this->assertSame('compact_day', $focus->resolveActive($worker, $org, null));
        $this->assertSame('compact_orders', $focus->resolveActive($admin, $org, null));

        // Die persönliche Wahl gewinnt (D16).
        $worker->setPreference(NavFocusService::PREFERENCE_KEY, 'compact_orders');
        $this->assertSame('compact_orders', $focus->resolveActive($worker->fresh(), $org, null));
        // Session gewinnt vor allem.
        $this->assertSame('all', $focus->resolveActive($worker->fresh(), $org, 'all'));
    }

    public function test_workspace_admin_saves_role_suggestions_only_for_offered_workspaces(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->post(route('admin.workspaces.save'), [
            'available' => ['time', 'sales'],
            'default' => 'time',
            'roles' => ['user' => 'time', 'admin' => 'facility'],
        ])->assertRedirect();

        $settings = (array) $org->fresh()->settings;
        $this->assertSame(['time', 'sales', 'all'], $settings[NavFocusService::SETTING_AVAILABLE]);
        $this->assertSame(['user' => 'time'], $settings[NavFocusService::SETTING_ROLES]);
    }

    public function test_onboarding_links_the_setup_dialog(): void {
        $org = Organization::factory()->pro()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->get(route('onboarding.index'))->assertOk()->assertSee(route('admin.scope.setup'), false);
    }
}
