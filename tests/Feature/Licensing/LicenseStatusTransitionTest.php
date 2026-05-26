<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseStatusTransitionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Models\AuditLog;
use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LicenseStatusTransitionTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
        Cache::flush();
        config(['license.cache_ttl' => 0]); // Cache pro Call vermeiden
    }

    public function test_no_audit_on_first_observation(): void {
        $service = $this->makeService(LicenseStatus::Valid);
        $service->current();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'license.expired']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'license.gracePeriodEntered']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'license.blocked']);
    }

    public function test_emits_expired_and_grace_when_transitioning_to_grace(): void {
        $service = $this->makeService(LicenseStatus::Valid);
        $service->current();

        $service2 = $this->makeService(LicenseStatus::GracePeriod);
        $service2->current();

        $this->assertSame(1, AuditLog::query()->where('event', 'license.expired')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'license.gracePeriodEntered')->count());
        $this->assertSame(0, AuditLog::query()->where('event', 'license.blocked')->count());
    }

    public function test_emits_blocked_when_transitioning_from_grace_to_expired(): void {
        $service = $this->makeService(LicenseStatus::GracePeriod);
        $service->current();

        $service2 = $this->makeService(LicenseStatus::Expired);
        $service2->current();

        $this->assertSame(1, AuditLog::query()->where('event', 'license.blocked')->count());
        // Kein zusätzliches license.expired, weil Grace -> Expired bereits "expired" impliziert.
        $this->assertSame(0, AuditLog::query()->where('event', 'license.expired')->count());
    }

    public function test_emits_expired_and_blocked_on_direct_valid_to_expired(): void {
        $service = $this->makeService(LicenseStatus::Valid);
        $service->current();

        $service2 = $this->makeService(LicenseStatus::Expired);
        $service2->current();

        $this->assertSame(1, AuditLog::query()->where('event', 'license.expired')->count());
        $this->assertSame(1, AuditLog::query()->where('event', 'license.blocked')->count());
    }

    public function test_no_duplicate_audit_on_repeated_same_status(): void {
        $service = $this->makeService(LicenseStatus::Valid);
        $service->current();

        $service2 = $this->makeService(LicenseStatus::GracePeriod);
        $service2->current();
        $service2->current();
        $service2->current();

        $this->assertSame(1, AuditLog::query()->where('event', 'license.gracePeriodEntered')->count());
    }

    private function makeService(LicenseStatus $status): LicenseService {
        return new class($status, app(Filesystem::class), app(CacheRepository::class)) extends LicenseService {
            public function __construct(
                private readonly LicenseStatus $status,
                Filesystem $files,
                CacheRepository $cache,
            ) {
                parent::__construct($files, $cache);
            }

            protected function evaluate(?string $host): LicenseResult {
                $payload = new LicensePayload(
                    licensee: 'TestCo',
                    email: null,
                    issuedAt: CarbonImmutable::now()->subYear(),
                    expiresAt: CarbonImmutable::now()->subDay(),
                    domain: null,
                    maxUsers: null,
                    maxOrgs: null,
                    storageQuotaGb: null,
                    features: [],
                    licenseId: 'test-license',
                );

                return new LicenseResult($this->status, $payload);
            }
        };
    }
}
