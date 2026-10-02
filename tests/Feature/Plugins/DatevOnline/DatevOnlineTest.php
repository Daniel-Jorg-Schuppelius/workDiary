<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\DatevOnline;

use App\Models\Customer\Customer;
use App\Models\Finance\DatevBookingBatch;
use App\Models\Invoicing\Invoice;
use App\Models\Platform\User;
use App\Plugins\DatevOnline\Api\DatevOnlineOAuth;
use App\Plugins\DatevOnline\Enums\{DatevConnectionStatus, DatevTransferKind, DatevTransferStatus};
use App\Plugins\DatevOnline\Models\{DatevOnlineConnection, DatevOnlineTransfer};
use App\Services\Finance\DatevBookingService;
use GuzzleHttp\{Client as GuzzleClient, HandlerStack};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** MVP-122: Login mit DATEV, Mandantenwahl, EXTF-Import mit Jobstatus, nächtlicher Belegupload. */
final class DatevOnlineTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const CLIENTS = 'https://accounting-clients.api.datev.de/platform-sandbox/v2/clients';

    private const EXTF = 'https://accounting-extf-files.api.datev.de/platform-sandbox/v3/clients/29098-55003/extf-files';

    private const DOCUMENTS = 'https://accounting-documents.api.datev.de/platform-sandbox/v2/clients/29098-55003/documents';

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->pluginSecret('datev-online', ['client_id' => 'wd-client', 'client_secret' => 'wd-secret', 'sandbox' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function connection(array $attributes = []): DatevOnlineConnection {
        return DatevOnlineConnection::query()->create($attributes + [
            'organization_id' => $this->organization->id,
            'access_token' => 'at-1',
            'refresh_token' => 'rt-1',
            'token_expires_at' => now()->addHour(),
            'status' => DatevConnectionStatus::Active,
            'datev_client_number' => '29098-55003',
            'datev_client_name' => 'Musterholz',
        ]);
    }

    public function test_login_uses_sandbox_with_pkce_nonce_and_scopes(): void {
        app()->instance(DatevOnlineOAuth::class, new DatevOnlineOAuth(new GuzzleClient(['handler' => HandlerStack::create(new MockHandler([
            new Psr7Response(200, ['Content-Type' => 'application/json'], (string) json_encode(['access_token' => 'at-new', 'refresh_token' => 'rt-new', 'expires_in' => 900, 'token_type' => 'Bearer'])),
        ]))])));

        $location = (string) $this->actingAs($this->admin)->post(route('admin.datev-online.oauth.start'))->headers->get('Location');
        $this->assertStringStartsWith('https://login.datev.de/openidsandbox/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('S256', $query['code_challenge_method'] ?? null);
        $this->assertNotEmpty($query['nonce'] ?? null);
        $this->assertStringContainsString('datev:accounting:extf-files-import', (string) ($query['scope'] ?? ''));
        $this->assertStringContainsString('offline_access', (string) ($query['scope'] ?? ''));

        $this->actingAs($this->admin)
            ->get(route('admin.datev-online.oauth.callback', ['state' => (string) $query['state'], 'code' => 'code-1']))
            ->assertRedirect(route('admin.datev-online.index'));
        $connection = DatevOnlineConnection::query()->firstOrFail();
        $this->assertTrue($connection->isActive());
        $this->assertSame('at-new', $connection->access_token);
        $this->assertSame(now()->toDateString(), $connection->documents_since?->toDateString(), 'Belege erst ab dem Verbinden');
    }

    public function test_client_selection_only_accepts_released_clients(): void {
        $this->connection(['datev_client_number' => null]);
        FakePluginHttp::fake([
            self::CLIENTS . '*' => [
                ['id' => '29098-55003', 'client_number' => 55003, 'consultant_number' => 29098, 'name' => 'Musterholz', 'services' => [['name' => 'accounting:documents', 'scopes' => ['datev:accounting:documents']]]],
            ],
        ]);

        $this->actingAs($this->admin)->get(route('admin.datev-online.index'))->assertOk()->assertSee('29098-55003 · Musterholz');
        $this->actingAs($this->admin)->post(route('admin.datev-online.client'), ['datev_client' => '29098-99999'])->assertSessionHasErrors('datev_client');
        $this->actingAs($this->admin)->post(route('admin.datev-online.client'), ['datev_client' => '29098-55003'])->assertSessionHas('success');
        $this->assertSame('Musterholz', DatevOnlineConnection::query()->firstOrFail()->datev_client_name);
    }

    public function test_finalised_batch_goes_to_datev_and_job_result_is_tracked(): void {
        Storage::fake(DatevBookingService::DISK);
        Storage::disk(DatevBookingService::DISK)->put('exports/finance/datev/test.csv', "\"EXTF\";700;21\n");
        $this->connection();
        $batch = DatevBookingBatch::factory()->exported()->create(['organization_id' => $this->organization->id, 'advisor_number' => 29098, 'client_number' => 55003]);
        $foreign = DatevBookingBatch::factory()->exported()->create(['organization_id' => $this->organization->id, 'advisor_number' => 29098, 'client_number' => 1]);
        $http = FakePluginHttp::fake([
            self::EXTF . '/import' => FakePluginHttp::response(null, 202, ['Location' => '/platform-sandbox/v3/clients/29098-55003/extf-files/jobs/job-1', 'Retry-After' => '5']),
            self::EXTF . '/jobs/job-1' => ['id' => 'job-1', 'result' => 'succeeded'],
        ]);

        $this->actingAs($this->admin)->post(route('admin.datev-online.batches.transfer', $foreign))->assertSessionHas('error', __('datev-online::datev.error.client_mismatch'));
        $this->actingAs($this->admin)->post(route('admin.datev-online.batches.transfer', $batch))->assertSessionHas('success');
        $http->assertSent(static fn (RequestInterface $request): bool => str_ends_with((string) $request->getUri(), '/extf-files/import')
            && $request->getHeaderLine('Filename') === 'test.csv'
            && $request->getHeaderLine('X-DATEV-Client-Id') === 'wd-client'
            && $request->getHeaderLine('Authorization') === 'Bearer at-1');

        $transfer = DatevOnlineTransfer::query()->where('kind', DatevTransferKind::Extf->value)->firstOrFail();
        $this->assertSame(DatevTransferStatus::Pending, $transfer->status);
        $this->assertSame('job-1', $transfer->datev_reference);

        // Kein zweiter Versand, solange der Job läuft.
        $this->actingAs($this->admin)->post(route('admin.datev-online.batches.transfer', $batch));
        $this->assertSame(1, DatevOnlineTransfer::query()->count());

        $this->actingAs($this->admin)->post(route('admin.datev-online.jobs.refresh'))->assertSessionHas('success');
        $this->assertSame(DatevTransferStatus::Succeeded, $transfer->refresh()->status);
    }

    public function test_nightly_sync_uploads_issued_invoices_once(): void {
        $this->connection(['is_documents_enabled' => true, 'documents_since' => now()->subDay()->toDateString()]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $issued = Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-2026-0300',
            'status' => Invoice::STATUS_ISSUED, 'type' => Invoice::TYPE_INVOICE, 'tax_rate' => '19.00', 'total' => '119.00',
            'issued_on' => now()->toDateString(), 'due_on' => now()->addDays(14)->toDateString(),
        ]);
        Invoice::query()->create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id, 'number' => 'R-2026-0001',
            'status' => Invoice::STATUS_ISSUED, 'type' => Invoice::TYPE_INVOICE, 'tax_rate' => '19.00', 'total' => '50.00',
            'issued_on' => now()->subMonth()->toDateString(),
        ]);
        $http = FakePluginHttp::fake([self::DOCUMENTS => FakePluginHttp::response(['id' => 'doc-1'], 201)]);

        $this->artisan('datev-online:sync')->assertSuccessful();
        $this->artisan('datev-online:sync')->assertSuccessful();

        $http->assertSentCount(1);
        $http->assertSent(static fn (RequestInterface $request): bool => str_contains((string) $request->getBody(), 'Rechnungsausgang')
            && str_contains((string) $request->getBody(), 'Rechnung-R-2026-0300.pdf'));
        $transfer = DatevOnlineTransfer::query()->firstOrFail();
        $this->assertSame(DatevTransferStatus::Transferred, $transfer->status);
        $this->assertSame((int) $issued->id, (int) $transfer->source_id);
        $this->assertSame('doc-1', $transfer->datev_reference);
    }
}
