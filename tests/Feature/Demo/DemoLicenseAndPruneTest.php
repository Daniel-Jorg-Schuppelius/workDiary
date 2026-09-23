<?php
/*
 * Created on   : Tue Sep 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoLicenseAndPruneTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Demo;

use App\Enums\Demo\DemoIndustry;
use App\Models\Audit\AuditLog;
use App\Models\Platform\{Organization, User};
use App\Services\Demo\DemoSeederService;
use App\Services\Licensing\{LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\Data\CryptoHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MVP-836: Demo-Organisationen bekommen auf einer Herausgeber-Instanz eine
 * befristete Org-Lizenz, die Tarif-Vorschau sagt die Wahrheit, und
 * `demo:prune` räumt nur abgelaufene Demo-Organisationen ab.
 */
final class DemoLicenseAndPruneTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        config()->set('license.cache_ttl', 0);
    }

    private function configureIssuerKeys(): void {
        $keypair = sodium_crypto_sign_keypair();
        config()->set('license.public_key', CryptoHelper::base64UrlEncode(sodium_crypto_sign_publickey($keypair)));
        config()->set('license.private_key', CryptoHelper::base64UrlEncode(sodium_crypto_sign_secretkey($keypair)));
    }

    public function test_fresh_org_gets_a_time_limited_org_license_when_the_instance_can_issue(): void {
        $this->configureIssuerKeys();
        config()->set('demo.license_days', 21);
        config()->set('demo.license_plan', 'enterprise');

        $platformAdmin = User::factory()->admin()->create(['is_platform_admin' => true]);
        $result = app(DemoSeederService::class)->freshOrg(DemoIndustry::Elektro, $platformAdmin);

        /** @var Organization $demo */
        $demo = $result['organization'];
        $this->assertNotEmpty($demo->license_key);

        $license = app(LicenseService::class)->forOrganization($demo);
        $this->assertSame(LicenseStatus::Valid, $license->status);
        $this->assertSame('enterprise', $license->payload?->plan);
        $this->assertSame('Demo Elektro', $license->payload?->licensee);
        $this->assertSame(
            CarbonImmutable::now()->addDays(21)->toDateString(),
            $license->payload?->expiresAt?->toDateString(),
        );

        $this->assertSame('organization', $result['counts']['license_source']);
        $this->assertSame('enterprise', $result['counts']['license_plan']);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $demo->id,
            'event' => 'demo.licensed',
            'user_id' => $platformAdmin->id,
        ]);
    }

    public function test_fresh_org_without_issuer_key_gets_no_license_and_reports_the_fallback(): void {
        config()->set('license.private_key', '');
        config()->set('license.private_key_path', '');

        $result = app(DemoSeederService::class)->freshOrg(DemoIndustry::ItService);

        /** @var Organization $demo */
        $demo = $result['organization'];
        $this->assertNull($demo->license_key);
        $this->assertDatabaseMissing('audit_logs', ['organization_id' => $demo->id, 'event' => 'demo.licensed']);
        // Testumgebung: Org-Plan gilt als Fallback (wie im FeatureFlagResolver).
        $this->assertSame('development', $result['counts']['license_source']);
        $this->assertSame('enterprise', $result['counts']['license_plan']);
    }

    public function test_license_outlook_is_free_in_production_without_any_usable_license(): void {
        config()->set('license.private_key', '');
        config()->set('license.private_key_path', '');
        config()->set('license.key', null);
        $seeder = app(DemoSeederService::class);
        $organization = Organization::factory()->create(['plan' => 'enterprise']);

        $previous = app()->environment();
        app()->detectEnvironment(static fn(): string => 'production');
        try {
            $this->assertSame(['source' => 'free', 'plan' => 'free'], $seeder->licenseOutlook($organization));
            $this->assertSame('free', $seeder->licenseOutlook()['source']);
        } finally {
            app()->detectEnvironment(static fn(): string => $previous);
        }

        // Mit Herausgeber-Schlüssel kündigt die Vorschau die Ausstellung an.
        $this->configureIssuerKeys();
        $this->assertSame('issuer', $seeder->licenseOutlook()['source']);
    }

    public function test_fresh_org_dialog_shows_the_license_outlook(): void {
        $this->configureIssuerKeys();
        $platformAdmin = User::factory()->admin()->create(['is_platform_admin' => true]);

        $this->actingAs($platformAdmin)
            ->get(route('admin.demo.fresh-org.create'))
            ->assertOk()
            ->assertSee('befristete Lizenz');
    }

    public function test_prune_deletes_only_expired_demo_organizations(): void {
        $seeder = app(DemoSeederService::class);
        $expired = $seeder->freshOrg(DemoIndustry::Facility)['organization'];
        $expired->forceFill(['demo_seeded_at' => CarbonImmutable::now()->subDays(45)])->save();
        $fresh = $seeder->freshOrg(DemoIndustry::Elektro)['organization'];
        $fresh->forceFill(['demo_seeded_at' => CarbonImmutable::now()->subDays(5)])->save();

        // Echte Organisation mit altem Datum bleibt unangetastet — is_demo entscheidet.
        $real = Organization::factory()->create(['is_demo' => false, 'demo_seeded_at' => CarbonImmutable::now()->subDays(400)]);

        // Ohne Frist: No-Op.
        config()->set('demo.retention_days', null);
        $this->artisan('demo:prune')->expectsOutputToContain('Keine Aufbewahrungsfrist')->assertSuccessful();
        $this->assertDatabaseHas('organizations', ['id' => $expired->id]);

        // Probelauf löscht nichts.
        $this->artisan('demo:prune', ['--days' => 30, '--dry-run' => true])
            ->expectsOutputToContain('Würde löschen')
            ->assertSuccessful();
        $this->assertDatabaseHas('organizations', ['id' => $expired->id]);

        $expiredId = (int) $expired->id;
        $this->artisan('demo:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $expiredId]);
        $this->assertDatabaseHas('organizations', ['id' => $fresh->id, 'is_demo' => true]);
        $this->assertDatabaseHas('organizations', ['id' => $real->id, 'is_demo' => false]);
        $this->assertTrue(AuditLog::query()->withoutGlobalScopes()
            ->where('organization_id', $expiredId)->where('event', 'demo.pruned')->exists());
    }

    public function test_prune_uses_the_configured_retention(): void {
        $seeder = app(DemoSeederService::class);
        $expired = $seeder->freshOrg(DemoIndustry::Spedition)['organization'];
        $expired->forceFill(['demo_seeded_at' => CarbonImmutable::now()->subDays(10)])->save();
        $expiredId = (int) $expired->id;

        config()->set('demo.retention_days', 7);
        $this->artisan('demo:prune')->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $expiredId]);
    }
}
