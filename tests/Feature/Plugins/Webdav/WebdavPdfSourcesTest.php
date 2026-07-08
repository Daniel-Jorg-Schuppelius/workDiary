<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WebdavPdfSourcesTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Webdav;

use App\Enums\Protocol\ProtocolStatus;
use App\Models\{Customer, IntegrationOutboxEntry, Invoice, Protocol, User, WebdavConnection};
use App\Plugins\Webdav\Contracts\{WebdavGateway, WebdavGatewayFactory};
use App\Plugins\Webdav\Services\WebdavOutboxDispatcher;
use App\Services\Invoicing\InvoicePdfRenderer;
use App\Services\Protocol\ProtocolPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Queue, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\RecordingWebdavGateway;
use Tests\TestCase;

/**
 * Feature 058, MVP-127, Rang 19: finalisierte Rechnungen (issued) und signierte
 * Protokolle als zusätzliche WebDAV-Mirror-Quellen, je Verbindung aktivierbar.
 * PDF-Renderer sind gefaked (kein dompdf im Test); der Fokus liegt auf Gating,
 * deterministischem Pfad und idempotenter Spiegelung über mirrorBytes().
 */
final class WebdavPdfSourcesTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        Storage::fake('local');
        Queue::fake();
    }

    /**
     * @param  list<string>  $sources
     */
    private function connection(array $sources): WebdavConnection {
        return WebdavConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Nextcloud',
            'base_url' => 'https://cloud.example.com/remote.php/dav',
            'username' => 'svc',
            'app_password' => 'secret',
            'default_folder' => 'Dokumente',
            'sources' => $sources,
            'active' => true,
        ]);
    }

    private function bindGateway(): RecordingWebdavGateway {
        $gateway = new RecordingWebdavGateway();
        $this->app->instance(WebdavGatewayFactory::class, new class($gateway) implements WebdavGatewayFactory {
            public function __construct(private WebdavGateway $gateway) {}

            public function for(WebdavConnection $connection): WebdavGateway {
                return $this->gateway;
            }
        });

        return $gateway;
    }

    private function fakeInvoiceRenderer(string $bytes = 'INVOICE-PDF-BYTES'): void {
        $this->app->instance(InvoicePdfRenderer::class, new class($bytes) extends InvoicePdfRenderer {
            public function __construct(private string $bytes) {}

            public function output(Invoice $invoice): string {
                return $this->bytes;
            }
        });
    }

    private function fakeProtocolRenderer(string $bytes = 'PROTOCOL-PDF-BYTES'): void {
        $this->app->instance(ProtocolPdfRenderer::class, new class($bytes) extends ProtocolPdfRenderer {
            public function __construct(private string $bytes) {}

            public function render(Protocol $protocol): string {
                $path = 'protocols/test/fake.pdf';
                Storage::disk(self::DISK)->put($path, $this->bytes);

                return $path;
            }
        });
    }

    private function issuedInvoice(): Invoice {
        return Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0042',
            'status' => Invoice::STATUS_ISSUED,
            'issued_on' => '2026-05-01',
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);
    }

    private function signedProtocol(): Protocol {
        return Protocol::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ProtocolStatus::Signed->value,
            'occurred_at' => '2026-06-15 08:00:00',
        ]);
    }

    public function test_issued_invoice_is_mirrored_as_pdf(): void {
        $this->connection(['invoice_pdf']);
        $gateway = $this->bindGateway();
        $this->fakeInvoiceRenderer('INVOICE-PDF-BYTES');
        $invoice = $this->issuedInvoice();

        $entry = IntegrationOutboxEntry::query()->where('operation', WebdavOutboxDispatcher::OP_MIRROR_INVOICE)->firstOrFail();
        $this->assertSame('mirror:invoice-' . $invoice->id . ':issued', $entry->idempotency_key);

        (new WebdavOutboxDispatcher())->dispatch($entry);

        $this->assertContains('invoices/2026/R2026-0042.pdf', $gateway->puts);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'webdav',
            'external_type' => 'invoice_pdf',
            'referenceable_id' => $invoice->id,
        ]);
    }

    public function test_signed_protocol_is_mirrored_as_pdf(): void {
        $this->connection(['protocol_pdf']);
        $gateway = $this->bindGateway();
        $this->fakeProtocolRenderer('PROTOCOL-PDF-BYTES');
        $protocol = $this->signedProtocol();

        $entry = IntegrationOutboxEntry::query()->where('operation', WebdavOutboxDispatcher::OP_MIRROR_PROTOCOL)->firstOrFail();
        (new WebdavOutboxDispatcher())->dispatch($entry);

        $this->assertContains('protocols/2026/protocol-' . $protocol->id . '.pdf', $gateway->puts);
        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'webdav',
            'external_type' => 'protocol_pdf',
            'referenceable_id' => $protocol->id,
        ]);
    }

    public function test_invoice_not_enqueued_when_source_inactive(): void {
        $this->connection(['document']); // invoice_pdf NICHT aktiv
        $this->issuedInvoice();

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('operation', WebdavOutboxDispatcher::OP_MIRROR_INVOICE)->count());
    }

    public function test_draft_invoice_not_enqueued(): void {
        $this->connection(['invoice_pdf']);
        Invoice::query()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2026-0099',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->admin->id,
        ]);

        $this->assertSame(0, IntegrationOutboxEntry::query()
            ->where('operation', WebdavOutboxDispatcher::OP_MIRROR_INVOICE)->count());
    }

    public function test_invoice_mirror_is_idempotent(): void {
        $this->connection(['invoice_pdf']);
        $gateway = $this->bindGateway();
        $this->fakeInvoiceRenderer('INVOICE-PDF-BYTES');
        $this->issuedInvoice();

        $entry = IntegrationOutboxEntry::query()->where('operation', WebdavOutboxDispatcher::OP_MIRROR_INVOICE)->firstOrFail();
        (new WebdavOutboxDispatcher())->dispatch($entry);
        (new WebdavOutboxDispatcher())->dispatch($entry); // Replay ohne Änderung

        $this->assertCount(1, $gateway->puts); // nur ein Upload
    }
}
