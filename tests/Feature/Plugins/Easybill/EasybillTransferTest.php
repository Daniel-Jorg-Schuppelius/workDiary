<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : EasybillTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Easybill;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, Organization, PluginSetting, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\Easybill\EasybillPlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\EasybillTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-431 (Phase 40): idempotente Übergabe als easybill-Rechnungsentwurf —
 * höchstens EIN Beleg je freigegebenem Transfer (POST /documents, Hoheit bei
 * easybill, /done wird nie gerufen); Preise in Cents; Timeout/Retry läuft
 * über die external_id-Reconciliation statt blinder Wiederholung.
 */
class EasybillTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    private BillingTransferService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'number' => 'K-1001',
            'email' => 'billing@acme.test',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::Easybill,
            'created_by' => $this->accountant->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'key-123'],
        ]);

        $this->service = app(BillingTransferService::class);
    }

    private function confirmedTransfer(): BillingTransfer {
        TimeEntry::create([
            'organization_id' => $this->organization->id,
            'project_id' => $this->project->id,
            'user_id' => $this->accountant->id,
            'date' => '2030-04-01',
            'minutes' => 120,
            'kind' => TimeEntryKind::Work->value,
            'billable' => true,
            'exported' => false,
            'hourly_rate' => '90.00',
        ]);

        $transfer = $this->service->createDraft(
            $this->customer,
            TransferChannel::Time,
            TransferTarget::Easybill,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
        $this->service->confirm($transfer, $this->accountant);

        return $transfer->fresh();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function stubs(array $overrides = []): array {
        return array_merge([
            // Reconciliation-Scan und Kunden-Suche treffen nichts.
            'https://api.easybill.de/rest/v1/documents?*' => FakePluginHttp::response(['items' => []]),
            'https://api.easybill.de/rest/v1/customers?*' => FakePluginHttp::response(['items' => []]),
            'https://api.easybill.de/rest/v1/customers' => FakePluginHttp::response(['id' => 501, 'number' => 'K-1001'], 201),
            'https://api.easybill.de/rest/v1/documents' => FakePluginHttp::response(['id' => 9001, 'type' => 'INVOICE', 'is_draft' => true], 201),
        ], $overrides);
    }

    public function test_execute_creates_exactly_one_easybill_invoice_draft_and_marks_sources(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)
            ->firstOrFail();
        $this->assertSame('9001', $reference->external_id);
        $this->assertSame(EasybillTarget::MARKER_PREFIX . $transfer->payload_hash, $reference->payload['marker'] ?? null);

        // Kunden-Projektion hat die Zuordnung persistiert.
        $contact = ExternalReference::query()
            ->where('plugin_id', EasybillPlugin::ID)
            ->where('external_type', EasybillTarget::EXT_TYPE_CUSTOMER)
            ->firstOrFail();
        $this->assertSame('501', $contact->external_id);

        // Quellen sind verbraucht (exported) — keine Doppelabrechnung.
        $this->assertSame(0, TimeEntry::query()->where('exported', false)->count());

        // Entwurf mit Quellmarker (external_id), Cents-Preis (90 €/h → 9000)
        // und Positionssteuer — atomar über POST /documents.
        $fake->assertSent(function (RequestInterface $r) use ($transfer): bool {
            if ($r->getMethod() !== 'POST' || ! str_ends_with((string) $r->getUri(), '/documents')) {
                return false;
            }
            $body = (string) $r->getBody();

            return str_contains($body, '"type":"INVOICE"')
                && str_contains($body, '"external_id":"' . EasybillTarget::MARKER_PREFIX . $transfer->payload_hash . '"')
                && str_contains($body, '"customer_id":501')
                && str_contains($body, '"single_price_net":9000')
                && str_contains($body, '"vat_percent":19');
        });

        // Rechnungshoheit bleibt easybill: Fertigstellung wird nie ausgelöst.
        $fake->assertNotSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), '/done'));

        // Recon-Scan + Kunden-Suche + Kunden-Anlage + EIN Document-Create = 4.
        $fake->assertSentCount(4);
    }

    public function test_reconciliation_adopts_existing_document_instead_of_duplicating(): void {
        $transfer = $this->confirmedTransfer();
        $marker = EasybillTarget::MARKER_PREFIX . $transfer->payload_hash;

        $fake = FakePluginHttp::fake($this->stubs([
            // Ein früherer, unklarer Lauf hat den Beleg bereits erzeugt.
            'https://api.easybill.de/rest/v1/documents?*' => FakePluginHttp::response(['items' => [
                ['id' => 'inv-77', 'external_id' => $marker],
            ]]),
        ]));

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)
            ->firstOrFail();
        $this->assertSame('inv-77', $reference->external_id);
        $this->assertTrue((bool) ($reference->payload['adopted_via_reconciliation'] ?? false));

        // KEIN schreibender Create — nur der lesende Scan.
        $fake->assertNotSent(fn(RequestInterface $r) => $r->getMethod() === 'POST');
    }

    public function test_second_execute_returns_existing_reference_without_new_document(): void {
        $transfer = $this->confirmedTransfer();

        FakePluginHttp::fake($this->stubs());
        $this->post(route('finance.transfers.execute', $transfer));

        // Direkter Zweitaufruf des Targets: liefert die bestehende Referenz,
        // ohne die API zu berühren.
        $fake = FakePluginHttp::fake([]);
        $result = app(EasybillTarget::class)->transfer($transfer->fresh());
        $this->assertSame('9001', $result->externalReference?->external_id);
        $fake->assertNothingSent();
    }

    public function test_einvoice_format_setting_adds_file_format_config(): void {
        PluginSetting::query()->delete();
        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'key-123', 'einvoice_format' => 'xrechnung3_0_xml'],
        ]);
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
        $fake->assertSent(fn(RequestInterface $r) => $r->getMethod() === 'POST'
            && str_ends_with((string) $r->getUri(), '/documents')
            && str_contains((string) $r->getBody(), '"file_format_config":[{"type":"xrechnung3_0_xml"}]'));
    }

    public function test_rate_limited_create_is_retried_via_retry_after(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs([
            // Erst 429 (Tarif-Limit), dann Erfolg — das Toolkit retried
            // anhand von Retry-After, ohne dass der Transfer fehlschlägt.
            'https://api.easybill.de/rest/v1/documents' => [
                FakePluginHttp::response(['message' => 'Too Many Requests'], 429, ['Retry-After' => '0']),
                FakePluginHttp::response(['id' => 9001, 'type' => 'INVOICE', 'is_draft' => true], 201),
            ],
        ]));

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
        $this->assertSame('9001', ExternalReference::query()
            ->where('external_type', EasybillTarget::EXT_TYPE_INVOICE)
            ->firstOrFail()->external_id);

        // Genau zwei Create-Versuche (429 → Wiederholung), kein dritter.
        $creates = array_filter($fake->recorded(), fn(array $entry): bool => $entry['request']->getMethod() === 'POST'
            && str_ends_with((string) $entry['request']->getUri(), '/documents'));
        $this->assertCount(2, $creates);
    }

    public function test_unconfigured_organization_fails_and_keeps_sources_free(): void {
        PluginSetting::query()->delete();
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake([]);

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('easybill', (string) $transfer->failure_reason);

        // Quellen bleiben frei (retry-fähig), die API wurde nie berührt.
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
        $fake->assertNothingSent();
    }

    public function test_settings_of_other_org_do_not_leak_into_transfer(): void {
        // Fremd-Org-Isolation: NUR eine fremde Organisation ist konfiguriert —
        // deren Key darf für die eigene Übergabe nie verwendet werden.
        PluginSetting::query()->delete();
        $other = Organization::factory()->create();
        PluginSetting::create([
            'organization_id' => $other->id,
            'plugin_id' => EasybillPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'key-foreign'],
        ]);

        $transfer = $this->confirmedTransfer();
        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('easybill', (string) $transfer->failure_reason);

        // Kein einziger Request mit dem Fremd-Key; Quellen bleiben frei.
        $fake->assertNothingSent();
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_create_dialog_offers_easybill_as_default_target(): void {
        $response = $this->get(route('finance.transfers.create', ['customer' => $this->customer->sqid]));

        $response->assertOk();
        $response->assertSee('value="easybill"', false);
        $response->assertSee('selected', false);
    }
}
