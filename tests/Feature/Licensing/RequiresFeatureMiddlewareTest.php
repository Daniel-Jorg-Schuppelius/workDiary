<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequiresFeatureMiddlewareTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RequiresFeatureMiddlewareTest extends TestCase {
    public function test_allows_request_when_feature_enabled(): void {
        $this->bindLicenseFeatures(['protocols.signed']);
        Route::middleware('requires-feature:protocols.signed')
            ->get('/_test-feature/protocols', fn() => response('ok'));

        $this->get('/_test-feature/protocols')->assertOk()->assertSee('ok');
    }

    public function test_blocks_request_with_html_423_when_feature_disabled(): void {
        $this->bindLicenseFeatures([]);
        Route::middleware('requires-feature:reports.export')
            ->get('/_test-feature/reports', fn() => response('ok'));

        $this->get('/_test-feature/reports')->assertStatus(423);
    }

    public function test_blocks_request_with_json_error_payload_when_feature_disabled(): void {
        $this->bindLicenseFeatures([]);
        Route::middleware('requires-feature:reports.export')
            ->get('/_test-feature/reports.json', fn() => response()->json(['ok' => true]));

        $response = $this->getJson('/_test-feature/reports.json');

        $response->assertStatus(423)
            ->assertJsonPath('error', 'feature_disabled')
            ->assertJsonPath('code', 'reports.export');
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
    }
}
