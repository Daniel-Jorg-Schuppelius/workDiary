<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ScopeGrowthTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Enums\Licensing\ModuleStatus;
use App\Models\Audit\AuditLog;
use App\Models\Platform\{LicenseFlagOverride, Organization, User};
use App\Services\Licensing\{FeatureFlagResolver, LicenseService, ModuleScopeService, ModuleStatusResolver};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dauerhafte Umfangswahl und kontrollierter Lizenzzuwachs (Feature 165, MVP-1188).
 */
class ScopeGrowthTest extends TestCase {
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

    public function test_package_preset_switches_to_limited_mode_and_remembers_chosen_modules(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        $this->scope()->applyPreset($org, 'start', $admin);

        $profile = $this->scope()->profile($org->fresh());
        $this->assertSame(ModuleScopeService::MODE_LIMITED, $profile['mode']);
        $this->assertSame('package', $profile['source']);
        $this->assertSame('start', $profile['key']);
        $this->assertSame(['module.vertrieb'], $profile['chosen']);
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.vertrieb'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.planung'));
    }

    public function test_plan_upgrade_keeps_new_modules_disabled_in_limited_mode(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);

        // Lizenzzuwachs: pro → enterprise bringt u. a. module.finance und module.lager.
        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.finance'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.lager'));
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.vertrieb'));
        $this->assertDatabaseHas('license_flag_overrides', [
            'organization_id' => $org->id,
            'flag' => 'module.finance',
            'reason' => ModuleScopeService::REASON_GROWTH,
        ]);
        $this->assertContains('module.finance', $this->scope()->growthModules($org->fresh()));

        // Web, Job-Gate und Resolver sehen dasselbe (eine Quelle).
        app(FeatureFlagResolver::class)->flush();
        $this->assertFalse(app(ModuleStatusResolver::class)->isActiveFor($org->fresh(), 'module.finance'));
        $this->actingAs($admin)->get(route('finance.transfers.index'))->assertStatus(423);
        $this->actingAs($admin)->get(route('customers.index'))->assertOk();
    }

    public function test_full_mode_activates_new_modules_immediately(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'voll', $admin);
        $this->assertSame(ModuleScopeService::MODE_FULL, $this->scope()->profile($org->fresh())['mode']);

        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.finance'));
        $this->assertDatabaseMissing('license_flag_overrides', ['organization_id' => $org->id, 'flag' => 'module.finance']);
    }

    public function test_organizations_without_profile_keep_the_previous_behaviour(): void {
        $org = Organization::factory()->pro()->create();
        app()->instance('currentOrganization', $org);
        $this->assertFalse($this->scope()->isLimited($org));

        $org->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.finance'));
        $this->assertSame([], $this->scope()->reconcile($org->fresh()));
    }

    public function test_reconcile_is_idempotent(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);
        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);

        $before = LicenseFlagOverride::query()->where('organization_id', $org->id)->count();
        $audits = AuditLog::query()->where('organization_id', $org->id)->where('event', 'license.moduleDisabled')->count();

        $this->assertSame([], $this->scope()->reconcile($org->fresh()));
        $this->assertSame([], $this->scope()->reconcile($org->fresh()));

        $this->assertSame($before, LicenseFlagOverride::query()->where('organization_id', $org->id)->count());
        $this->assertSame($audits, AuditLog::query()->where('organization_id', $org->id)->where('event', 'license.moduleDisabled')->count());
    }

    public function test_enabling_a_module_through_the_service_makes_it_chosen(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);

        $this->assertTrue($this->scope()->enableModule($org->fresh(), 'module.planung', $admin));

        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertContains('module.planung', $this->scope()->profile($org->fresh())['chosen']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event' => 'license.moduleEnabled']);

        // Ein späterer Lizenzzuwachs lässt das gewählte Modul in Ruhe.
        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.planung'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.finance'));
    }

    public function test_disabling_a_module_through_the_service_removes_it_from_chosen(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'team', $admin);
        $this->assertContains('module.planung', $this->scope()->profile($org->fresh())['chosen']);

        $this->assertTrue($this->scope()->disableModule($org->fresh(), 'module.planung', $admin, 'nicht benötigt'));

        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.planung'));
        $this->assertNotContains('module.planung', $this->scope()->profile($org->fresh())['chosen']);
        // Nicht lizenzierte oder schon deaktivierte Module lassen sich nicht „deaktivieren".
        $this->assertFalse($this->scope()->disableModule($org->fresh(), 'module.finance', $admin));
    }

    public function test_switching_modes_freezes_active_modules_and_never_activates_by_itself(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);

        $this->scope()->setMode($org, ModuleScopeService::MODE_LIMITED, $admin);
        $profile = $this->scope()->profile($org->fresh());
        $this->assertSame(ModuleScopeService::MODE_LIMITED, $profile['mode']);
        $this->assertContains('module.planung', $profile['chosen']);
        $this->assertNotContains('module.finance', $profile['chosen']);

        $org->fresh()->update(['plan' => Organization::PLAN_ENTERPRISE]);
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.finance'));

        // Zurück auf Vollumfang: bestehende Deaktivierungen bleiben (E8).
        $this->scope()->setMode($org->fresh(), ModuleScopeService::MODE_FULL, $admin);
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.finance'));
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $org->id, 'event' => 'license.scopeConfigured']);
    }

    public function test_platform_wide_override_survives_enable(): void {
        $org = Organization::factory()->enterprise()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        LicenseFlagOverride::query()->create(['organization_id' => null, 'flag' => 'module.chat', 'disabled_at' => CarbonImmutable::now()]);

        $this->assertFalse($this->scope()->enableModule($org, 'module.chat', $admin));

        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.chat'));
        $this->assertDatabaseHas('license_flag_overrides', ['organization_id' => null, 'flag' => 'module.chat']);
    }

    public function test_license_installation_triggers_reconcile(): void {
        $keypair = sodium_crypto_sign_keypair();
        config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
        config()->set('license.cache_ttl', 0);

        $org = Organization::factory()->free()->create();
        $admin = $this->admin($org);
        app()->instance('currentOrganization', $org);
        $this->scope()->applyPreset($org, 'start', $admin);

        $payload = [
            'license_id' => bin2hex(random_bytes(8)),
            'licensee' => 'Kleinbetrieb',
            'issued_at' => CarbonImmutable::now()->toIso8601String(),
            'expires_at' => CarbonImmutable::now()->addYear()->toIso8601String(),
            'plan' => 'free',
            'addons' => ['module.vertrieb', 'module.planung'],
            'organization' => $org->license_uid,
            'package' => 'start',
        ];
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sig = sodium_crypto_sign_detached($json, sodium_crypto_sign_secretkey($keypair));
        $key = CryptoHelper::base64UrlEncode($json) . '.' . CryptoHelper::base64UrlEncode($sig);

        app(LicenseService::class)->installForOrganization($org->fresh(), $key);

        $this->assertSame(ModuleStatus::Active, $this->moduleStatus($org, 'module.vertrieb'));
        $this->assertSame(ModuleStatus::InactiveByCustomer, $this->moduleStatus($org, 'module.planung'));
        $this->assertDatabaseHas('license_flag_overrides', ['organization_id' => $org->id, 'flag' => 'module.planung', 'reason' => ModuleScopeService::REASON_GROWTH]);
    }

    public function test_mode_can_be_switched_on_the_scope_page(): void {
        $org = Organization::factory()->pro()->create();
        $admin = $this->admin($org);

        $this->actingAs($admin)->get(route('admin.scope.index'))->assertOk()->assertSee(__('scope.mode.heading'));
        $this->actingAs($admin)->from(route('admin.scope.index'))
            ->post(route('admin.scope.mode'), ['scope_mode' => ModuleScopeService::MODE_LIMITED])
            ->assertRedirect(route('admin.scope.index'));

        $this->assertTrue($this->scope()->isLimited($org->fresh()));

        $this->actingAs($admin)->from(route('admin.scope.index'))
            ->post(route('admin.scope.mode'), ['scope_mode' => 'halb'])
            ->assertSessionHasErrors('scope_mode');
    }
}
