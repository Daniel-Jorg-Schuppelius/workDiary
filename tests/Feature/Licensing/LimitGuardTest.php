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
use App\Models\{Attachment, DiaryEntry, Organization, User};
use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus, LimitGuard};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class LimitGuardTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
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

    public function test_org_limit_passes_when_under(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null, maxOrgs: 5)));
        // setUpOrganization() created one organization; 1 < 5.

        app(LimitGuard::class)->ensureCanCreateOrganization($this->organization);

        $this->assertTrue(true);
    }

    public function test_org_limit_throws_and_audits_when_reached(): void {
        Organization::factory()->count(2)->create();
        $current = Organization::query()->count();
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null, maxOrgs: $current)));

        try {
            app(LimitGuard::class)->ensureCanCreateOrganization($this->organization);
            $this->fail('LimitExceededException erwartet.');
        } catch (LimitExceededException $e) {
            $this->assertSame('max_orgs', $e->limit);
            $this->assertSame($current, $e->current);
            $this->assertSame($current, $e->max);
        }

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'limit.exceeded',
        ]);
    }

    public function test_storage_quota_passes_when_no_quota(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null, storageQuotaGb: null)));

        app(LimitGuard::class)->ensureCanStoreAttachment($this->organization, 10_000_000);

        $this->assertTrue(true);
    }

    public function test_storage_quota_passes_when_under(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null, storageQuotaGb: 1)));

        app(LimitGuard::class)->ensureCanStoreAttachment($this->organization, 1024);

        $this->assertTrue(true);
    }

    public function test_storage_quota_throws_when_upload_exceeds_limit(): void {
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null, storageQuotaGb: 1)));

        $entry = DiaryEntry::factory()->create(['organization_id' => $this->organization->id]);
        Attachment::factory()->create([
            'organization_id' => $this->organization->id,
            'attachable_type' => DiaryEntry::class,
            'attachable_id' => $entry->id,
            'size' => 1024 * 1024 * 1024 - 1000, // 1 GB minus 1000 B
        ]);

        try {
            app(LimitGuard::class)->ensureCanStoreAttachment($this->organization, 2000);
            $this->fail('LimitExceededException erwartet.');
        } catch (LimitExceededException $e) {
            $this->assertSame('storage_quota_gb', $e->limit);
        }

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->organization->id,
            'event' => 'limit.exceeded',
        ]);
    }

    /**
     * Vollaudit 2026-07 (H8): max_orgs greift jetzt auch an der öffentlichen
     * Registrierung — vorher entstand Org+Admin ohne jeden Lizenz-Check.
     */
    public function test_public_registration_respects_org_limit(): void {
        config(['app.registration_enabled' => true]);
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(
            maxUsers: null,
            maxOrgs: Organization::query()->count(), // Limit bereits erreicht
        )));

        $before = Organization::query()->count();
        $this->post('/register', [
            'org_name' => 'Neue Firma',
            'name' => 'Max Muster',
            'email' => 'max@example.test',
            'password' => 'Geheim-Passwort-1234!',
            'password_confirmation' => 'Geheim-Passwort-1234!',
        ])->assertSessionHasErrors('org_name');

        $this->assertSame($before, Organization::query()->count());
        $this->assertDatabaseMissing('users', ['email' => 'max@example.test']);
    }

    /** Vollaudit 2026-07 (N9): Auslastungs-Snapshot für die Limit-Frühwarnung. */
    public function test_user_limit_usage_reports_current_and_max(): void {
        User::factory()->count(2)->create(['organization_id' => $this->organization->id]);
        $current = $this->organization->activeUserCount();
        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: $current + 1)));

        $usage = app(LimitGuard::class)->userLimitUsage($this->organization);

        $this->assertSame(['current' => $current, 'max' => $current + 1], $usage);

        $this->bindLicense(LicenseResult::ok(LicenseStatus::Valid, $this->payload(maxUsers: null)));
        $this->assertNull(app(LimitGuard::class)->userLimitUsage($this->organization), 'Ohne Limit keine Warnung.');
    }

    private function bindLicense(LicenseResult $result, bool $enforced = true): void {
        $stub = new class($result, $enforced) extends LicenseService {
            public function __construct(private readonly LicenseResult $result, private readonly bool $enforced) {}
            public function isEnforced(): bool { return $this->enforced; }
            public function current(?string $host = null): LicenseResult { return $this->result; }
        };
        $this->app->instance(LicenseService::class, $stub);
    }

    private function payload(?int $maxUsers, ?int $maxOrgs = null, ?int $storageQuotaGb = null): LicensePayload {
        return new LicensePayload(
            licensee: 'TestCo',
            email: null,
            issuedAt: CarbonImmutable::now()->subDay(),
            expiresAt: CarbonImmutable::now()->addYear(),
            domain: null,
            maxUsers: $maxUsers,
            maxOrgs: $maxOrgs,
            storageQuotaGb: $storageQuotaGb,
            features: [],
            licenseId: 'test-' . bin2hex(random_bytes(4)),
        );
    }
}
