<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillDocumentPullTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Easybill;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, Document, ExternalReference, PluginSetting, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Easybill\EasybillPlugin;
use App\Plugins\Easybill\Services\EasybillDocumentPullService;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\EasybillTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-431 (W1.3): Rückabruf fertiggestellter easybill-Belege ins DMS —
 * PDF landet als DocumentVersion am BillingTransfer, der Nachweis (sha256 +
 * Abrufzeitpunkt) in der ExternalReference-Payload; je Beleg genau EIN Pull,
 * Entwürfe bleiben pending.
 */
class EasybillDocumentPullTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private BillingTransfer $transfer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);

        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'number' => 'K-1001',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Easybill,
            'created_by' => $this->accountant->id,
        ]);
        $project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => 60,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'key-123'],
        ]);

        $this->transfer = app(BillingTransferService::class)->createDraft(
            $customer,
            TransferChannel::Time,
            TransferTarget::Easybill,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
    }

    /** @param array<string, mixed> $payload */
    private function reference(array $payload = []): ExternalReference {
        return ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'external_type' => EasybillTarget::EXT_TYPE_INVOICE,
            'referenceable_type' => $this->transfer->getMorphClass(),
            'referenceable_id' => $this->transfer->getKey(),
            'external_id' => 'inv-9001',
            'payload' => array_merge(['source' => 'easybill', 'marker' => 'workdiary:x'], $payload),
            'synced_at' => now(),
        ]);
    }

    public function test_pull_stores_finished_document_with_hash_proof(): void {
        $reference = $this->reference();
        $pdf = '%PDF-1.4 easybill-test';

        FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/documents/inv-9001/pdf' => FakePluginHttp::response($pdf, 200, ['Content-Type' => 'application/pdf']),
            'https://api.easybill.de/rest/v1/documents/inv-9001' => FakePluginHttp::response(['id' => 'inv-9001', 'is_draft' => false, 'number' => 'RE-1001']),
        ]);

        $result = app(EasybillDocumentPullService::class)->pull($this->organization->id);

        $this->assertSame(['checked' => 1, 'pulled' => 1, 'pending' => 0], $result);

        $document = Document::query()
            ->where('documentable_type', $this->transfer->getMorphClass())
            ->where('documentable_id', $this->transfer->getKey())
            ->firstOrFail();
        $this->assertStringContainsString('RE-1001', (string) $document->title);
        $version = $document->versions()->firstOrFail();
        $this->assertSame('easybill-RE-1001.pdf', $version->original_name);
        $this->assertSame(strlen($pdf), (int) $version->size);

        // Nachweis in der Referenz: sha256 + Abrufzeitpunkt + finale Nummer.
        $payload = (array) $reference->fresh()->payload;
        $this->assertSame(hash('sha256', $pdf), $payload['document_sha256'] ?? null);
        $this->assertNotEmpty($payload['document_pulled_at'] ?? null);
        $this->assertSame('RE-1001', $payload['document']['number'] ?? null);
        $this->assertSame($document->id, $payload['dms_document_id'] ?? null);
    }

    public function test_pull_runs_exactly_once_per_document(): void {
        $this->reference([
            'document_pulled_at' => now()->toIso8601String(),
            'document_sha256' => 'abc',
        ]);

        $fake = FakePluginHttp::fake([]);

        $result = app(EasybillDocumentPullService::class)->pull($this->organization->id);

        $this->assertSame(['checked' => 0, 'pulled' => 0, 'pending' => 0], $result);
        $fake->assertNothingSent();
        $this->assertSame(0, Document::query()->count());
    }

    public function test_draft_documents_stay_pending_without_download(): void {
        $this->reference();

        $fake = FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/documents/inv-9001' => FakePluginHttp::response(['id' => 'inv-9001', 'is_draft' => true]),
        ]);

        $result = app(EasybillDocumentPullService::class)->pull($this->organization->id);

        $this->assertSame(['checked' => 1, 'pulled' => 0, 'pending' => 1], $result);
        $this->assertSame(0, Document::query()->count());
        $fake->assertNotSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), '/pdf'));
    }

    public function test_disabled_pull_setting_skips_organization(): void {
        $this->reference();
        // Model-Update (nicht Query-Builder) — der encrypted:array-Cast muss greifen.
        PluginSetting::query()->firstOrFail()->update(['settings' => ['api_key' => 'key-123', 'pull_documents' => false]]);

        $fake = FakePluginHttp::fake([]);

        $result = app(EasybillDocumentPullService::class)->pull($this->organization->id);

        $this->assertSame(['checked' => 0, 'pulled' => 0, 'pending' => 0], $result);
        $fake->assertNothingSent();
    }

    public function test_einvoice_format_prefers_download_endpoint(): void {
        $this->reference();
        PluginSetting::query()->firstOrFail()->update(['settings' => ['api_key' => 'key-123', 'einvoice_format' => 'xrechnung3_0_xml']]);
        $xml = '<?xml version="1.0"?><Invoice/>';

        FakePluginHttp::fake([
            'https://api.easybill.de/rest/v1/documents/inv-9001/download' => FakePluginHttp::response($xml, 200, ['Content-Type' => 'application/xml;format=xrechnung;version=3.0.1']),
            'https://api.easybill.de/rest/v1/documents/inv-9001/pdf' => FakePluginHttp::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf']),
            'https://api.easybill.de/rest/v1/documents/inv-9001' => FakePluginHttp::response(['id' => 'inv-9001', 'is_draft' => false, 'number' => 'RE-1001']),
        ]);

        $result = app(EasybillDocumentPullService::class)->pull($this->organization->id);

        $this->assertSame(1, $result['pulled']);
        // Primärabruf bleibt die XRechnung vom /download-Endpunkt (v1);
        // seit N25 (Vollaudit 2026-07) wird zusätzlich das PDF als zweite
        // Dokumentversion archiviert — versions() sortiert version_no DESC.
        $document = Document::query()->firstOrFail();
        $xmlVersion = $document->versions()->where('version_no', 1)->firstOrFail();
        $this->assertSame('easybill-RE-1001.xml', $xmlVersion->original_name);
        $pdfVersion = $document->versions()->where('version_no', 2)->firstOrFail();
        $this->assertSame('easybill-RE-1001.pdf', $pdfVersion->original_name);
        $payload = (array) \App\Models\ExternalReference::query()->firstOrFail()->payload;
        $this->assertSame(hash('sha256', '%PDF-1.4 fake'), $payload['document_pdf_sha256'] ?? null);
    }
}
