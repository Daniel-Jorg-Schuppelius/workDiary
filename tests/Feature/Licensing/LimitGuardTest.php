<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LimitGuardTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Exceptions\LimitExceededException;
use App\Models\User;
use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus, LimitGuard};
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LimitGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
    }

    public function test_passes_when_license_not_enforced(): void {
        $this->bindLicense(LicenseResult::fail(LicenseStatus::Missing), enforced: false);
        User::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        app(LimitGuard::class)->ensureCanCreateUser($this->organization);

        $this->assertTrue(true);
    }

    public function test_passes_when_no_max_users_set(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null)));
        User::factory()->count(2)->create(['organization_id' => $this->organization->id]);

        app(LimitGuard::class)->ensureCanCreateUser($this->organization);

        $this->assertTrue(true);
    }

    public function test_passes_when_under_limit(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: 100)));
        User::factory()->count(3)->create(['organization_id' => $this->organization->id]);

        app(LimitGuard::class)->ensureCanCreateUser($this->organization);

        $this->assertTrue(true);
    }

    public function test_throws_and_audits_when_at_limit(): void {
        User::factory()->count(3)->create(['organization_id' => $this->organization->id]);
        $current = User::query()->count();
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: $current)));

        try {
            app(LimitGuard::class)->ensureCanCreateUser($this->organization);
            $this->fail('LimitExceededException erwartet.');
        } catch (LimitExceededException $e) {
            $this->assertSame('max_users', $e->limit);
            $this->assertSame($current, $e->current);
            $this->assertSame($current, $e->max);
        }

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'limit.exceeded',
        ]);
    }

    private function bindLicense(LicenseResult $result, bool $enforced = true): void {
        $stub = new class($result, $enforced) extends LicenseService {
            public function __construct(private readonly LicenseResult $result, private readonly bool $enforced) {}
            public function isEnforced(): bool { return $this->enforced; }
            public function current(?string $host = null): LicenseResult { return $this->result; }
        };
        $this->app->instance(LicenseService::class, $stub);
    }

    private function payload(?int $maxUsers): LicensePayload {
        return new LicensePayload(
            licensee: 'TestCo',
            email: null,
            issuedAt: CarbonImmutable::now()->subDay(),
            expiresAt: CarbonImmutable::now()->addYear(),
            domain: null,
            maxUsers: $maxUsers,
            features: [],
            licenseId: 'test-' . bin2hex(random_bytes(4)),
        );
    }
}
