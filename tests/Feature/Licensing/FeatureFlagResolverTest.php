<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureFlagResolverTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\{FeatureFlagResolver, LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FeatureFlagResolverTest extends TestCase {
    public function test_unknown_feature_returns_false(): void {
        $this->bindLicense(LicenseResult::fail(LicenseStatus::Missing));
        $resolver = app()->make(FeatureFlagResolver::class);

        $this->assertFalse($resolver->isEnabled('does.not.exist'));
    }

    public function test_features_from_license_payload_are_enabled(): void {
        $this->bindLicense($this->validLicenseWithFeatures(['protocols.signed', 'reports.export']));
        $resolver = app()->make(FeatureFlagResolver::class);

        $this->assertTrue($resolver->isEnabled('protocols.signed'));
        $this->assertTrue($resolver->isEnabled('reports.export'));
        $this->assertFalse($resolver->isEnabled('procedures.fourEyes'));
    }

    public function test_env_override_wins_over_license(): void {
        $this->bindLicense($this->validLicenseWithFeatures(['protocols.signed']));
        config(['license.feature_overrides' => [
            'protocols.signed' => false,
            'manual.feature' => true,
        ]]);

        $resolver = app()->make(FeatureFlagResolver::class);

        $this->assertFalse($resolver->isEnabled('protocols.signed'));
        $this->assertTrue($resolver->isEnabled('manual.feature'));
    }

    public function test_all_returns_complete_resolved_map(): void {
        $this->bindLicense($this->validLicenseWithFeatures(['a', 'b']));
        config(['license.feature_overrides' => ['c' => true, 'b' => false]]);

        $resolver = app()->make(FeatureFlagResolver::class);
        $all = $resolver->all();

        $this->assertSame(['a' => true, 'b' => false, 'c' => true], $all);
    }

    private function bindLicense(LicenseResult $result): void {
        $stub = new class($result) extends LicenseService {
            public function __construct(private readonly LicenseResult $result) {
                // bewusst kein parent::__construct() — keine Dateioperationen nötig.
            }

            public function isEnforced(): bool { return true; }

            public function current(?string $host = null): LicenseResult {
                return $this->result;
            }
        };

        $this->app->instance(LicenseService::class, $stub);
    }

    private function validLicenseWithFeatures(array $features): LicenseResult {
        return LicenseResult::ok(
            LicenseStatus::Valid,
            new LicensePayload(
                licensee: 'TestCo',
                email: null,
                issuedAt: CarbonImmutable::now()->subDay(),
                expiresAt: CarbonImmutable::now()->addYear(),
                domain: null,
                maxUsers: 50,
                maxOrgs: null,
                storageQuotaGb: null,
                features: $features,
                licenseId: 'test-' . bin2hex(random_bytes(4)),
            )
        );
    }
}
