<?php
/*
 * Created on   : Tue Jun 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgLicensingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Models\{Organization, User};
use App\Services\Licensing\{FeatureFlagResolver, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Org-gebundene Lizenzen: Tier + Add-on-Module, Bindung, Hart-Free ohne Lizenz. */
class OrgLicensingTest extends TestCase {
    use RefreshDatabase;

    private string $secretKey;

    protected function setUp(): void {
        parent::setUp();
        $keypair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keypair);
        config()->set('license.public_key', base64_encode(sodium_crypto_sign_publickey($keypair)));
        config()->set('license.cache_ttl', 0);
    }

    /** @param array<string,mixed> $payload */
    private function signed(array $payload): string {
        $json = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sig = sodium_crypto_sign_detached($json, $this->secretKey);

        return LicenseService::b64Encode($json) . '.' . LicenseService::b64Encode($sig);
    }

    /** @param array<string,mixed> $overrides */
    private function keyFor(Organization $org, array $overrides = []): string {
        return $this->signed(array_merge([
            'license_id' => bin2hex(random_bytes(8)),
            'licensee' => 'Testkunde',
            'issued_at' => CarbonImmutable::now()->toIso8601String(),
            'expires_at' => CarbonImmutable::now()->addYear()->toIso8601String(),
            'plan' => 'pro',
            'addons' => [],
            'organization' => $org->license_uid,
        ], $overrides));
    }

    private function resolverFor(Organization $org): FeatureFlagResolver {
        app()->instance('currentOrganization', $org);
        $resolver = app(FeatureFlagResolver::class);
        $resolver->flush();

        return $resolver;
    }

    public function test_install_syncs_plan_and_enables_tier_modules(): void {
        $org = Organization::factory()->create(['plan' => 'free']);
        $service = app(LicenseService::class);

        $result = $service->installForOrganization($org, $this->keyFor($org));
        $this->assertSame(LicenseStatus::Valid, $result->status);

        $org->refresh();
        $this->assertSame('pro', $org->plan); // Tier aus der Lizenz synchronisiert

        $resolver = $this->resolverFor($org);
        $this->assertTrue($resolver->isEnabled('module.datenschutz')); // im pro-Tier
        $this->assertFalse($resolver->isEnabled('module.lohn'));       // nur enterprise
    }

    public function test_addon_unlocks_single_module_beyond_tier(): void {
        $org = Organization::factory()->create(['plan' => 'free']);
        $service = app(LicenseService::class);
        $service->installForOrganization($org, $this->keyFor($org, ['plan' => 'pro', 'addons' => ['module.lohn']]));

        $resolver = $this->resolverFor($org->refresh());
        $this->assertTrue($resolver->isEnabled('module.lohn')); // einzeln gebucht
    }

    public function test_license_bound_to_other_org_is_rejected(): void {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $keyForA = $this->keyFor($orgA);

        // orgB versucht, die auf orgA gebundene Lizenz zu nutzen.
        $orgB->forceFill(['license_key' => $keyForA])->save();
        $result = app(LicenseService::class)->forOrganization($orgB);

        $this->assertSame(LicenseStatus::OrgMismatch, $result->status);
        $this->assertFalse($result->isUsable());
    }

    public function test_no_license_is_hard_free_in_production(): void {
        $org = Organization::factory()->create(['plan' => 'enterprise']);
        $this->app['env'] = 'production';

        $resolver = $this->resolverFor($org);
        // Ohne nutzbare Lizenz greift produktiv hart free – Plan-Feld wird ignoriert.
        $this->assertFalse($resolver->isEnabled('module.datenschutz'));
        $this->assertFalse($resolver->isEnabled('module.kanban'));

        $this->app['env'] = 'testing';
    }

    public function test_admin_ui_installs_and_removes_org_license(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = $admin->organization;
        $org->update(['plan' => 'free']);

        // Einspielen ueber die Admin-Route.
        $this->actingAs($admin)
            ->post(route('admin.license.org.install'), ['license_key' => $this->keyFor($org, ['plan' => 'enterprise'])])
            ->assertRedirect();

        $org->refresh();
        $this->assertSame('enterprise', $org->plan); // Tier synchronisiert
        $this->assertNotNull($org->license_key);

        // Entfernen ueber die Admin-Route.
        $this->actingAs($admin)
            ->delete(route('admin.license.org.remove'))
            ->assertRedirect();
        $this->assertNull($org->refresh()->license_key);
    }

    public function test_issue_for_organization_signs_and_installs(): void {
        config()->set('license.private_key', base64_encode($this->secretKey));
        $service = app(LicenseService::class);
        $this->assertTrue($service->canIssue());

        $org = Organization::factory()->create(['plan' => 'free']);
        $result = $service->issueForOrganization($org, 'pro', ['module.lohn'], null, 'Selbst ausgestellt');

        $this->assertSame(LicenseStatus::Valid, $result->status);
        $this->assertSame('pro', $org->refresh()->plan);

        $resolver = $this->resolverFor($org);
        $this->assertTrue($resolver->isEnabled('module.datenschutz')); // pro-Tier
        $this->assertTrue($resolver->isEnabled('module.lohn'));        // Add-on
    }

    public function test_admin_ui_issues_license(): void {
        config()->set('license.private_key', base64_encode($this->secretKey));
        $admin = User::factory()->platformAdmin()->create();
        $org = $admin->organization;
        $org->update(['plan' => 'free']);

        $this->actingAs($admin)->post(route('admin.license.org.issue'), [
            'licensee' => 'ACME',
            'plan' => 'enterprise',
            'addons' => [],
        ])->assertRedirect();

        $org->refresh();
        $this->assertSame('enterprise', $org->plan);
        $this->assertNotNull($org->license_key);
    }

    public function test_issuer_signs_key_for_customer_without_installing(): void {
        config()->set('license.private_key', base64_encode($this->secretKey));
        $service = app(LicenseService::class);
        $customer = Organization::factory()->create();

        $key = $service->signLicense('enterprise', ['module.lohn'], null, 'Kunde GmbH', $customer->license_uid);
        $this->assertIsString($key);

        // Gültig für den Kunden, aber NICHT lokal installiert.
        $verified = $service->verify($key, null, (string) $customer->license_uid);
        $this->assertSame(LicenseStatus::Valid, $verified->status);
        $this->assertSame('enterprise', $verified->payload?->plan);
        $this->assertNull($customer->fresh()?->license_key);
    }

    public function test_issuer_console_route_flashes_key(): void {
        config()->set('license.private_key', base64_encode($this->secretKey));
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.license.issuer.create'), ['licensee' => 'Kunde GmbH', 'plan' => 'pro'])
            ->assertRedirect()
            ->assertSessionHas('issued_key');
    }

    public function test_no_license_falls_back_to_org_plan_in_testing(): void {
        $org = Organization::factory()->create(['plan' => 'enterprise']);

        $resolver = $this->resolverFor($org);
        // local/testing: organizations.plan dient als Fallback (Dev/Bestandstests).
        $this->assertTrue($resolver->isEnabled('module.datenschutz'));
        $this->assertTrue($resolver->isEnabled('module.lohn'));
    }
}
