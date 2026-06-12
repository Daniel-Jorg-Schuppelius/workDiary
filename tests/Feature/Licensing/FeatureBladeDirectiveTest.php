<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FeatureBladeDirectiveTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class FeatureBladeDirectiveTest extends TestCase {
    use RefreshDatabase;

    public function test_directive_check_is_true_when_feature_enabled(): void {
        $this->bindLicenseFeatures(['protocols.signed']);

        // Blade::check ist die zugrundeliegende API, die @feature(code) im
        // kompilierten Blade-Template aufruft. Der Compiler-Pfad selbst
        // ist Laravel-intern und wird separat getestet — hier verifizieren
        // wir, dass das in AppServiceProvider registrierte Callback die
        // erwartete Wahrheits-Auflösung liefert.
        $this->assertTrue(Blade::check('feature', 'protocols.signed'));
    }

    public function test_directive_check_is_false_when_feature_disabled(): void {
        $this->bindLicenseFeatures([]);

        $this->assertFalse(Blade::check('feature', 'protocols.signed'));
    }

    public function test_directive_check_respects_env_override(): void {
        $this->bindLicenseFeatures(['protocols.signed']);
        config(['license.feature_overrides' => ['protocols.signed' => false]]);
        $this->app->forgetInstance(\App\Services\Licensing\FeatureFlagResolver::class);

        $this->assertFalse(Blade::check('feature', 'protocols.signed'));
    }

    private function bindLicenseFeatures(array $features): void {
        $stub = new class(LicenseResult::ok(LicenseStatus::Valid, new LicensePayload(
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
        ))) extends LicenseService {
            public function __construct(private readonly LicenseResult $result) {}
            public function isEnforced(): bool { return true; }
            public function current(?string $host = null): LicenseResult { return $this->result; }
        };
        $this->app->instance(LicenseService::class, $stub);
        $this->app->forgetInstance(\App\Services\Licensing\FeatureFlagResolver::class);
    }
}
