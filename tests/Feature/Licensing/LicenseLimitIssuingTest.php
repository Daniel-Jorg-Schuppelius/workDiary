<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LicenseLimitIssuingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Licensing;

use App\Exceptions\LimitExceededException;
use App\Models\Attachments\Attachment;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Services\Licensing\{LicenseService, LimitGuard};
use App\Support\MorphMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** MVP-1618: max_orgs und storage_quota_gb lassen sich ausstellen und greifen. */
class LicenseLimitIssuingTest extends TestCase {
    use RefreshDatabase;

    private string $privateB64;

    protected function setUp(): void {
        parent::setUp();
        $keypair = sodium_crypto_sign_keypair();
        $this->privateB64 = base64_encode(sodium_crypto_sign_secretkey($keypair));
        config([
            'license.public_key' => base64_encode(sodium_crypto_sign_publickey($keypair)),
            'license.private_key' => $this->privateB64,
            'license.cache_ttl' => 0,
        ]);
    }

    public function test_issue_command_signs_both_limits(): void {
        $path = storage_path('app/testing/limit-' . bin2hex(random_bytes(4)) . '.lic');
        try {
            $this->artisan('license:issue', [
                '--licensee' => 'Beispiel Hosting GmbH',
                '--private-key' => $this->privateB64,
                '--max-orgs' => 3,
                '--storage-quota-gb' => 20,
                '--out' => $path,
            ])->assertExitCode(0);

            $payload = app(LicenseService::class)->verify(trim((string) file_get_contents($path)))->payload;
            $this->assertSame(3, $payload?->maxOrgs);
            $this->assertSame(20, $payload?->storageQuotaGb);
        } finally {
            @unlink($path);
        }

        $this->artisan('license:issue', ['--licensee' => 'X', '--private-key' => $this->privateB64, '--max-orgs' => 'viele'])->assertExitCode(1);
    }

    public function test_issuer_console_signs_limits(): void {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)->post(route('admin.license.issuer.create'), [
            'licensee' => 'Kunde GmbH',
            'plan' => 'pro',
            'max_orgs' => 5,
            'storage_quota_gb' => 50,
        ])->assertSessionHas('issued_key');

        $payload = app(LicenseService::class)->verify((string) $response->getSession()->get('issued_key'))->payload;
        $this->assertSame(5, $payload?->maxOrgs);
        $this->assertSame(50, $payload?->storageQuotaGb);
        $this->assertDatabaseHas('audit_logs', ['event' => 'license.keyIssued']);

        $this->actingAs($admin)->from(route('admin.license.issuer'))->post(route('admin.license.issuer.create'), [
            'licensee' => 'Kunde GmbH',
            'plan' => 'pro',
            'storage_quota_gb' => 0,
        ])->assertSessionHasErrors('storage_quota_gb');
    }

    public function test_org_license_quota_is_issued_and_enforced_for_that_organization(): void {
        $admin = User::factory()->platformAdmin()->create();
        $org = $admin->organization;

        $this->actingAs($admin)->post(route('admin.license.org.issue'), [
            'licensee' => 'ACME',
            'plan' => 'pro',
            'max_users' => 40,
            'storage_quota_gb' => 1,
        ])->assertRedirect();

        $payload = app(LicenseService::class)->forOrganization($org->refresh())->payload;
        $this->assertSame(1, $payload?->storageQuotaGb);
        $this->assertSame(40, $payload?->maxUsers);

        $entry = DiaryEntry::factory()->create(['organization_id' => $org->id]);
        Attachment::factory()->create([
            'organization_id' => $org->id,
            'attachable_type' => MorphMap::alias(DiaryEntry::class),
            'attachable_id' => $entry->id,
            'size' => 1024 * 1024 * 1024 - 1000,
        ]);
        $this->enforce();

        $this->expectException(LimitExceededException::class);
        app(LimitGuard::class)->ensureCanStoreAttachment($org, 2000);
    }

    public function test_platform_organization_creation_stops_at_max_orgs(): void {
        $admin = User::factory()->platformAdmin()->create();
        $current = Organization::query()->count();
        $key = app(LicenseService::class)->signLicense('enterprise', [], null, 'Betreiber', null, null, null, null, null, $current);
        $this->assertIsString($key);
        config(['license.key' => $key]);
        $this->enforce();

        $this->actingAs($admin)->from(route('admin.organizations.index'))->post(route('admin.organizations.store'), [
            'name' => 'Zu viel GmbH',
            'plan' => 'free',
            'locale' => 'de',
            'timezone' => 'Europe/Berlin',
        ])->assertSessionHasErrors('name');

        $this->assertSame($current, Organization::query()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'limit.exceeded']);
    }

    /** Lizenzprüfung wie in Produktion erzwingen; Signatur und Bindung bleiben echt. */
    private function enforce(): void {
        config(['license.enforce' => true]);
    }
}
