<?php
/*
 * Created on   : Mon Jun 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UserLimitEnforcementTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Licensing;

use App\Models\User;
use App\Services\Licensing\{LicensePayload, LicenseResult, LicenseService, LicenseStatus};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Durchsetzung des Lizenz-Nutzerlimits bei der Mitglieder-Anlage über die
 * Org-Admin-Oberfläche (Feature 021).
 */
class UserLimitEnforcementTest extends TestCase {
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();
    }

    /** Bindet eine Lizenz mit gegebenem max_users an den Container. */
    private function bindLicense(?int $maxUsers, bool $enforced = true): void {
        $payload = $maxUsers === null ? null : new LicensePayload(
            licensee: 'TestCo',
            email: null,
            issuedAt: CarbonImmutable::now()->subDay(),
            expiresAt: CarbonImmutable::now()->addYear(),
            domain: null,
            maxUsers: $maxUsers,
            maxOrgs: null,
            storageQuotaGb: null,
            features: [],
            licenseId: 'test-' . bin2hex(random_bytes(4)),
        );
        $result = $payload === null
            ? LicenseResult::fail(LicenseStatus::Missing)
            : LicenseResult::ok(LicenseStatus::Valid, $payload);

        $stub = new class($result, $enforced) extends LicenseService {
            public function __construct(private readonly LicenseResult $result, private readonly bool $enforced) {}
            public function isEnforced(): bool { return $this->enforced; }
            public function current(?string $host = null): LicenseResult { return $this->result; }
        };
        $this->app->instance(LicenseService::class, $stub);
    }

    /**
     * @return array<string, mixed>
     */
    private function memberPayload(): array {
        return [
            'name' => 'Neues Mitglied',
            'email' => 'neu-' . bin2hex(random_bytes(4)) . '@example.test',
            'role' => 'user',
            'password' => 'Sup3r-Secret!2026',
            'password_confirmation' => 'Sup3r-Secret!2026',
        ];
    }

    public function test_blocks_member_creation_when_user_limit_reached(): void {
        $admin = User::factory()->admin()->create();
        $org = $admin->organization;
        // Org hat bereits 1 Nutzer (Admin). Limit = 1 → erreicht.
        $this->bindLicense(maxUsers: 1);

        $before = $org->activeUserCount();

        $response = $this->actingAs($admin)
            ->from(route('org.members.index'))
            ->post(route('org.members.store'), $this->memberPayload());

        $response->assertRedirect(route('org.members.index'));
        $response->assertSessionHasErrors('limit');
        $this->assertSame($before, $org->refresh()->activeUserCount());

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'event' => 'limit.exceeded',
        ]);
    }

    public function test_allows_member_creation_under_limit(): void {
        $admin = User::factory()->admin()->create();
        $org = $admin->organization;
        $this->bindLicense(maxUsers: 50);

        $before = $org->activeUserCount();

        $this->actingAs($admin)
            ->post(route('org.members.store'), $this->memberPayload())
            ->assertRedirect(route('org.members.index'))
            ->assertSessionHas('success');

        $this->assertSame($before + 1, $org->refresh()->activeUserCount());
    }

    public function test_unlimited_license_does_not_block(): void {
        $admin = User::factory()->admin()->create();
        $org = $admin->organization;
        // Bestehende Nutzer auffüllen, dann ohne max_users (unbegrenzt) anlegen.
        User::factory()->count(5)->create(['organization_id' => $org->id]);
        $this->bindLicense(maxUsers: null);

        $before = $org->activeUserCount();

        $this->actingAs($admin)
            ->post(route('org.members.store'), $this->memberPayload())
            ->assertRedirect(route('org.members.index'))
            ->assertSessionHas('success');

        $this->assertSame($before + 1, $org->refresh()->activeUserCount());
    }
}
