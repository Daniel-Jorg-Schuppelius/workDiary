<?php
/*
 * Created on   : Sat Oct 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DatevOnlineIncomingInvoiceTargetTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\DatevOnline;

use App\Enums\Billing\DocumentDirection;
use App\Enums\Document\DocumentType;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceTransferStatus};
use App\Models\Customer\Customer;
use App\Models\Document\{Document, DocumentVersion};
use App\Models\Invoicing\{IncomingEInvoice, IncomingEInvoiceTransfer};
use App\Models\Platform\User;
use App\Models\Supplier\Supplier;
use App\Plugins\DatevOnline\DatevOnlinePlugin;
use App\Plugins\DatevOnline\Enums\DatevConnectionStatus;
use App\Plugins\DatevOnline\Models\DatevOnlineConnection;
use App\Services\Invoicing\EInvoice\IncomingInvoiceTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPluginSecrets};
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/** Feature 163, MVP-1111 (E16): Rechnungseingang als Belegbild an DATEV Unternehmen online. */
final class DatevOnlineIncomingInvoiceTargetTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPluginSecrets;

    private const DOCUMENTS = 'https://accounting-documents.api.datev.de/platform-sandbox/v2/clients/29098-55003/documents';

    private User $admin;

    /** @var list<string> */
    private array $uploads = [];

    protected function setUp(): void {
        parent::setUp();
        Storage::fake('local');
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
        $this->pluginSecret('datev-online', ['client_id' => 'wd-client', 'client_secret' => 'wd-secret', 'sandbox' => true]);
        DatevOnlineConnection::query()->create([
            'organization_id' => $this->organization->id, 'access_token' => 'at-1', 'refresh_token' => 'rt-1',
            'token_expires_at' => now()->addHour(), 'status' => DatevConnectionStatus::Active,
            'datev_client_number' => '29098-55003', 'datev_client_name' => 'Musterholz',
            'is_documents_enabled' => true, 'documents_since' => now()->subDay()->toDateString(),
        ]);
    }

    public function test_assigned_receipt_goes_once_as_rechnungseingang_with_its_own_guid(): void {
        $fake = $this->fakeDatev();
        $incoming = $this->incoming();

        app(IncomingInvoiceTransferService::class)->transfer($incoming);
        app(IncomingInvoiceTransferService::class)->retryOpen($this->organization);

        $journal = IncomingEInvoiceTransfer::query()->sole();
        $this->assertSame(DatevOnlinePlugin::ID, $journal->target);
        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $journal->external_id);
        $fake->assertSentCount(1);
        $fake->assertSent(static fn (RequestInterface $r): bool => $r->getMethod() === 'PUT' && str_ends_with((string) $r->getUri(), '/documents/' . $journal->external_id));
        $this->assertStringContainsString('Rechnungseingang', $this->uploads[0]);
        $this->assertStringContainsString('<Invoice', $this->uploads[0]);
    }

    public function test_a_known_guid_counts_as_delivered(): void {
        $this->fakeDatev(FakePluginHttp::response(['error' => 'conflict'], 409));
        $incoming = $this->incoming();

        [$journal] = app(IncomingInvoiceTransferService::class)->transfer($incoming);

        $this->assertSame(IncomingInvoiceTransferStatus::Transferred, $journal->status);
        $this->assertNotNull($incoming->refresh()->transferred_at);
    }

    public function test_outgoing_copies_go_as_rechnungsausgang_and_older_receipts_stay(): void {
        $this->fakeDatev();
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $outgoing = $this->incoming(['supplier_id' => null, 'customer_id' => $customer->id, 'direction' => DocumentDirection::Outgoing]);
        $old = $this->incoming(['received_at' => now()->subWeek()]);

        app(IncomingInvoiceTransferService::class)->transfer($outgoing);
        $this->assertSame([], app(IncomingInvoiceTransferService::class)->transfer($old));

        $this->assertStringContainsString('Rechnungsausgang', $this->uploads[0]);
        $this->assertSame(1, IncomingEInvoiceTransfer::query()->count());
    }

    public function test_the_admin_page_lists_receipts_from_the_shared_journal(): void {
        $this->fakeDatev();
        app(IncomingInvoiceTransferService::class)->transfer($this->incoming());
        FakePluginHttp::fake(['https://accounting-clients.api.datev.de/*' => []]);

        $rows = $this->get(route('admin.datev-online.index'))->assertOk()->viewData('documentTransfers');

        $this->assertCount(1, $rows);
        $this->assertSame(__('datev-online::datev.transfer_kind.incoming_document'), $rows[0]['kind']);
    }

    private function fakeDatev(?\GuzzleHttp\Psr7\Response $response = null): FakePluginHttp {
        return FakePluginHttp::fake([
            self::DOCUMENTS . '/*' => function (RequestInterface $request) use ($response): \GuzzleHttp\Psr7\Response {
                $this->uploads[] = (string) $request->getBody();

                return $response ?? FakePluginHttp::response(['id' => 'doc-1'], 201);
            },
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private function incoming(array $attributes = []): IncomingEInvoice {
        $path = 'documents/' . uniqid('er', true) . '.xml';
        Storage::disk('local')->put($path, '<?xml version="1.0"?><Invoice>ER</Invoice>');
        $document = Document::factory()->create(['organization_id' => $this->organization->id, 'document_type' => DocumentType::Invoice, 'title' => 'Eingangsrechnung ER']);
        $version = DocumentVersion::factory()->create(['document_id' => $document->id, 'path' => $path, 'original_name' => 'er.xml', 'mime' => 'application/xml', 'uploaded_by_user_id' => $this->admin->id]);
        $document->forceFill(['current_version_id' => $version->id])->save();
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);

        return IncomingEInvoice::query()->create([
            'organization_id' => $this->organization->id, 'document_id' => $document->id, 'sha256' => hash('sha256', $path),
            'source' => 'mail', 'received_at' => now(), 'status' => IncomingEInvoiceStatus::Received, 'supplier_id' => $supplier->id,
            'invoice_number' => 'ER-' . uniqid(), 'seller_name' => 'Holz AG', 'currency' => 'EUR', 'amount_gross' => '119.00', 'summary' => [],
            ...$attributes,
        ]);
    }
}
