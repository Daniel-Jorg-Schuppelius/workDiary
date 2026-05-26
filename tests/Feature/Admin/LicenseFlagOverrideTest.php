<?php
/*
 * Created on   : Sat Nov 22 2025
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseFlagOverrideTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Admin;

use App\Models\{AuditLog, LicenseFlagOverride, User};
use App\Services\Licensing\{FeatureFlagResolver, LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseFlagOverrideTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $payload = new LicensePayload(
            licensee: 'ACME Co',
            email: null,
            issuedAt: CarbonImmutable::parse('2025-01-01'),
            expiresAt: CarbonImmutable::parse('2099-01-01'),
            domain: null,
            maxUsers: null,
            maxOrgs: null,
            storageQuotaGb: null,
            features: ['plugins.experimental', 'reports.advanced'],
            licenseId: 'lic-1',
        );
        $result = LicenseResult::ok(LicenseStatus::Valid, $payload, 'ok');

        $service = $this->createStub(LicenseService::class);
        $service->method('current')->willReturn($result);
        $service->method('isEnforced')->willReturn(true);
        $this->app->instance(LicenseService::class, $service);
    }

    public function test_toggle_disables_a_licensed_flag_and_logs_audit(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.flags.toggle', ['flag' => 'plugins.experimental']), [
                'reason' => 'Sicherheits-Hotfix',
            ]);

        $response->assertRedirect(route('admin.license.index'));
        $this->assertDatabaseHas('license_flag_overrides', [
            'organization_id' => $admin->organization_id,
            'flag' => 'plugins.experimental',
            'reason' => 'Sicherheits-Hotfix',
            'disabled_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'license.flagDisabled',
            'organization_id' => $admin->organization_id,
            'user_id' => $admin->id,
        ]);

        $resolver = $this->app->make(FeatureFlagResolver::class);
        $this->assertFalse($resolver->isEnabled('plugins.experimental'));
        $this->assertTrue($resolver->isEnabled('reports.advanced'));
    }

    public function test_toggle_removes_existing_override_and_logs_restore(): void {
        $admin = User::factory()->admin()->create();
        LicenseFlagOverride::query()->create([
            'organization_id' => $admin->organization_id,
            'flag' => 'plugins.experimental',
            'reason' => 'Vorher',
            'disabled_at' => CarbonImmutable::now(),
            'disabled_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.flags.toggle', ['flag' => 'plugins.experimental']))
            ->assertRedirect(route('admin.license.index'));

        $this->assertDatabaseMissing('license_flag_overrides', [
            'organization_id' => $admin->organization_id,
            'flag' => 'plugins.experimental',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'license.flagRestored',
            'organization_id' => $admin->organization_id,
        ]);
    }

    public function test_cannot_disable_a_non_licensed_flag(): void {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.license.index'))
            ->post(route('admin.license.flags.toggle', ['flag' => 'unknown.feature']));

        $response->assertRedirect(route('admin.license.index'));
        $response->assertSessionHasErrors('flag');
        $this->assertDatabaseMissing('license_flag_overrides', [
            'flag' => 'unknown.feature',
        ]);
        $this->assertSame(0, AuditLog::query()->where('event', 'like', 'license.%')->count());
    }

    public function test_forbidden_for_regular_user(): void {
        $user = User::factory()->user()->create();

        $this->actingAs($user)
            ->post(route('admin.license.flags.toggle', ['flag' => 'plugins.experimental']))
            ->assertForbidden();
    }

    public function test_resolver_ignores_global_override_for_non_licensed_flag(): void {
        // Option A Garantie: Override schaltet NIEMALS nicht-lizenzierte
        // Features frei — und ein Override für ein nicht-lizenziertes
        // Feature darf den Resolver auch nicht beeinflussen.
        LicenseFlagOverride::query()->create([
            'organization_id' => null,
            'flag' => 'unknown.feature',
            'disabled_at' => CarbonImmutable::now(),
        ]);

        $resolver = $this->app->make(FeatureFlagResolver::class);
        $this->assertFalse($resolver->isEnabled('unknown.feature'));
        $this->assertTrue($resolver->isEnabled('plugins.experimental'));
    }
}
