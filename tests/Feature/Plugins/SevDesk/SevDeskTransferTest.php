<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\SevDesk;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, Organization, PluginSetting, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\SevDesk\Api\SevDeskClient;
use App\Plugins\SevDesk\SevDeskPlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\SevDeskTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-125 (Bauturbo A4): idempotente Übergabe als sevDesk-Rechnungsentwurf —
 * höchstens EINE Rechnung je freigegebenem Transfer (Status 50, Hoheit bei
 * sevDesk); Timeout/Retry läuft über die Marker-Reconciliation statt blinder
 * Wiederholung; Quellen werden markiert; Buchhaltungs-Version je Mandant
 * steuert taxRule (2.0) vs. taxType (1.0).
 */
class SevDeskTransferTest extends TestCase {
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
            'billing_mode' => BillingMode::SevDesk,
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
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-123'],
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
            TransferTarget::SevDesk,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
        $this->service->confirm($transfer, $this->accountant);

        return $transfer->fresh();
    }

    /** @param array<string, mixed> $overrides */
    private function stubs(array $overrides = []): array {
        return array_merge([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => '1.0']]),
            'https://my.sevdesk.de/api/v1/SevUser*' => FakePluginHttp::response(['objects' => [['id' => 7]]]),
            // Kontakt-Suche über die Kundennummer trifft nichts → Projektion legt an.
            'https://my.sevdesk.de/api/v1/Contact?*' => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Contact' => FakePluginHttp::response(['objects' => ['id' => 501, 'customerNumber' => 'K-1001']], 201),
            // Reconciliation-Scan findet nichts.
            'https://my.sevdesk.de/api/v1/Invoice?*' => FakePluginHttp::response(['objects' => []]),
            'https://my.sevdesk.de/api/v1/Invoice/Factory/saveInvoice' => FakePluginHttp::response(['objects' => ['invoice' => ['id' => 9001, 'status' => '50']]], 201),
        ], $overrides);
    }

    public function test_execute_creates_exactly_one_sevdesk_invoice_draft_and_marks_sources(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('external_type', SevDeskTarget::EXT_TYPE_INVOICE)
            ->firstOrFail();
        $this->assertSame('9001', $reference->external_id);
        $this->assertSame(SevDeskTarget::MARKER_PREFIX . $transfer->payload_hash, $reference->payload['marker'] ?? null);

        // Kontakt-Projektion hat die Zuordnung persistiert.
        $contact = ExternalReference::query()
            ->where('plugin_id', SevDeskPlugin::ID)
            ->where('external_type', SevDeskTarget::EXT_TYPE_CONTACT)
            ->firstOrFail();
        $this->assertSame('501', $contact->external_id);

        // Quellen sind verbraucht (exported) — keine Doppelabrechnung.
        $this->assertSame(0, TimeEntry::query()->where('exported', false)->count());

        // Entwurf (Status 50, Hoheit bei sevDesk) mit Marker, Kontakt und
        // v1-Steuerlogik (taxType statt taxRule) — atomar über die Factory.
        $fake->assertSent(function (RequestInterface $r) use ($transfer): bool {
            if ($r->getMethod() !== 'POST' || ! str_contains((string) $r->getUri(), '/Invoice/Factory/saveInvoice')) {
                return false;
            }
            $body = (string) $r->getBody();

            return str_contains($body, '"status":50')
                && str_contains($body, SevDeskTarget::MARKER_PREFIX . $transfer->payload_hash)
                && str_contains($body, '"taxType":"default"')
                && ! str_contains($body, '"taxRule"')
                && str_contains($body, '"objectName":"InvoicePos"')
                && str_contains($body, '"id":501')
                && str_contains($body, '"objectName":"SevUser"');
        });

        // Version-Probe + Recon-Scan + Kontakt-Suche + Kontakt-Anlage +
        // SevUser + EIN Invoice-Create = 6 Requests.
        $fake->assertSentCount(6);
    }

    public function test_reconciliation_adopts_existing_invoice_instead_of_duplicating(): void {
        $transfer = $this->confirmedTransfer();
        $marker = SevDeskTarget::MARKER_PREFIX . $transfer->payload_hash;

        $fake = FakePluginHttp::fake($this->stubs([
            // Ein früherer, unklarer Lauf hat die Rechnung bereits erzeugt.
            'https://my.sevdesk.de/api/v1/Invoice?*' => FakePluginHttp::response(['objects' => [
                ['id' => 'inv-77', 'customerInternalNote' => 'Übergabe [' . $marker . ']'],
            ]]),
        ]));

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('external_type', SevDeskTarget::EXT_TYPE_INVOICE)
            ->firstOrFail();
        $this->assertSame('inv-77', $reference->external_id);
        $this->assertTrue((bool) ($reference->payload['adopted_via_reconciliation'] ?? false));

        // KEIN schreibender Create — nur der lesende Scan.
        $fake->assertNotSent(fn(RequestInterface $r) => $r->getMethod() === 'POST');
    }

    public function test_second_execute_returns_existing_reference_without_new_invoice(): void {
        $transfer = $this->confirmedTransfer();

        FakePluginHttp::fake($this->stubs());
        $this->post(route('finance.transfers.execute', $transfer));

        // Direkter Zweitaufruf des Targets: liefert die bestehende Referenz,
        // ohne die API zu berühren.
        $fake = FakePluginHttp::fake([]);
        $result = app(SevDeskTarget::class)->transfer($transfer->fresh());
        $this->assertSame('9001', $result->externalReference?->external_id);
        $fake->assertNothingSent();
    }

    public function test_bookkeeping_version_two_switches_invoice_to_tax_rule(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs([
            'https://my.sevdesk.de/api/v1/Tools/bookkeepingSystemVersion*' => FakePluginHttp::response(['objects' => ['version' => '2.0']]),
        ]));

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
        $this->assertSame('2.0', Cache::get(SevDeskClient::versionCacheKey($this->organization->id)));

        $fake->assertSent(function (RequestInterface $r): bool {
            if (! str_contains((string) $r->getUri(), '/Invoice/Factory/saveInvoice')) {
                return false;
            }
            $body = (string) $r->getBody();

            return str_contains($body, '"taxRule":{"id":1,"objectName":"TaxRule"}')
                && ! str_contains($body, '"taxType"');
        });
    }

    public function test_cached_bookkeeping_version_skips_version_call(): void {
        $transfer = $this->confirmedTransfer();
        Cache::put(SevDeskClient::versionCacheKey($this->organization->id), '1.0', 600);

        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Transferred, $transfer->fresh()->status);
        // Versions-Cache je Mandant greift — keine erneute API-Probe.
        $fake->assertNotSent(fn(RequestInterface $r) => str_contains((string) $r->getUri(), 'bookkeepingSystemVersion'));
        $fake->assertSentCount(5);
    }

    public function test_contact_projection_adopts_existing_contact_by_customer_number(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake($this->stubs([
            'https://my.sevdesk.de/api/v1/Contact?*' => FakePluginHttp::response(['objects' => [
                ['id' => 555, 'customerNumber' => 'K-1001', 'name' => 'ACME'],
            ]]),
        ]));

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $contact = ExternalReference::query()
            ->where('external_type', SevDeskTarget::EXT_TYPE_CONTACT)
            ->firstOrFail();
        $this->assertSame('555', $contact->external_id);

        // Kein Kontakt-Create — Matching statt Schattenstammdaten-Dublette.
        $fake->assertNotSent(fn(RequestInterface $r) => $r->getMethod() === 'POST'
            && str_ends_with((string) $r->getUri(), '/Contact'));
    }

    public function test_unconfigured_organization_fails_and_keeps_sources_free(): void {
        PluginSetting::query()->delete();
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake([]);

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('sevDesk', (string) $transfer->failure_reason);

        // Quellen bleiben frei (retry-fähig), die API wurde nie berührt.
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
        $fake->assertNothingSent();
    }

    public function test_settings_of_other_org_do_not_leak_into_transfer(): void {
        // Fremd-Org-Isolation: NUR eine fremde Organisation ist konfiguriert —
        // deren Token darf für die eigene Übergabe nie verwendet werden.
        PluginSetting::query()->delete();
        $other = Organization::factory()->create();
        PluginSetting::create([
            'organization_id' => $other->id,
            'plugin_id' => SevDeskPlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'tok-foreign'],
        ]);

        $transfer = $this->confirmedTransfer();
        $fake = FakePluginHttp::fake($this->stubs());

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('sevDesk', (string) $transfer->failure_reason);

        // Kein einziger Request mit dem Fremd-Token; Quellen bleiben frei.
        $fake->assertNothingSent();
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
        $this->assertSame(0, ExternalReference::query()->count());
    }

    public function test_create_dialog_offers_sevdesk_as_default_target(): void {
        $response = $this->get(route('finance.transfers.create', ['customer' => $this->customer->sqid]));

        $response->assertOk();
        $response->assertSee('value="sevdesk"', false);
        $response->assertSee('selected', false);
    }
}
