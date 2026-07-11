<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxTransferTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\OrgaMax;

use App\Enums\Finance\{BillingMode, TransferChannel, TransferStatus, TransferTarget};
use App\Enums\Project\ProjectStatus;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\{Customer, ExternalReference, OrgaMaxConnection, Project, TimeEntry, User};
use App\Models\Finance\BillingTransfer;
use App\Plugins\OrgaMax\OrgaMaxPlugin;
use App\Services\Finance\BillingTransferService;
use App\Services\Finance\Targets\OrgaMaxTarget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-309/315: idempotente Übergabe als orgaMAX-Auftrag — höchstens EIN
 * Auftrag je freigegebenem Transfer; Timeout/Retry läuft über die
 * Marker-Reconciliation statt blinder Wiederholung; Quellen werden markiert.
 */
class OrgaMaxTransferTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    private Project $project;

    private OrgaMaxConnection $connection;

    private BillingTransferService $service;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'email' => 'billing@acme.test',
            'currency' => 'EUR',
            'hourly_rate' => '90.00',
            'billing_mode' => BillingMode::OrgaMax,
            'created_by' => $this->accountant->id,
        ]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'name' => 'Web',
            'status' => ProjectStatus::Active->value,
            'created_by' => $this->accountant->id,
        ]);

        $this->connection = OrgaMaxConnection::create([
            'organization_id' => $this->organization->id,
            'mode' => OrgaMaxConnection::MODE_PRIVATE,
            'api_key' => 'k',
            'api_secret' => 's',
            'ownership_id' => 'own-1',
            'bearer_token' => 'token',
            'token_expires_at' => Carbon::now()->addHour(),
            'status' => OrgaMaxConnection::STATUS_ACTIVE,
            'capabilities' => ['billing' => ['enabled' => true, 'leader' => 'orgamax']],
        ]);

        // Kunde ist einem orgaMAX-Kunden zugeordnet (Voraussetzung, MVP-307).
        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => OrgaMaxPlugin::ID,
            'external_type' => 'customer',
            'referenceable_type' => $this->customer->getMorphClass(),
            'referenceable_id' => $this->customer->id,
            'external_id' => 'om-cust-1',
            'payload' => [],
            'synced_at' => now(),
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
            TransferTarget::OrgaMax,
            ['from' => '2030-04-01', 'to' => '2030-04-30'],
            null,
            $this->accountant,
        );
        $this->service->confirm($transfer, $this->accountant);

        return $transfer->fresh();
    }

    public function test_execute_creates_exactly_one_orgamax_order_and_marks_sources(): void {
        $transfer = $this->confirmedTransfer();

        $fake = FakePluginHttp::fake([
            // Reconciliation-Scan findet nichts.
            'https://api.orgamax.de/openapi/order?*' => FakePluginHttp::response([]),
            'https://api.orgamax.de/openapi/order/*' => FakePluginHttp::response(['id' => 'om-order-1'], 201),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('plugin_id', OrgaMaxPlugin::ID)
            ->where('external_type', OrgaMaxTarget::EXT_TYPE_ORDER)
            ->firstOrFail();
        $this->assertSame('om-order-1', $reference->external_id);
        $this->assertSame(OrgaMaxTarget::MARKER_PREFIX . $transfer->payload_hash, $reference->payload['marker'] ?? null);

        // Quellen sind verbraucht (exported) — keine Doppelabrechnung.
        $this->assertSame(0, TimeEntry::query()->where('exported', false)->count());

        // Genau ZWEI Requests: lesender Reconciliation-Scan + EIN Order-Create.
        $fake->assertSentCount(2);
        $fake->assertSent(fn(RequestInterface $r) => $r->getMethod() === 'POST' && str_contains((string) $r->getUri(), '/order/'));
    }

    public function test_reconciliation_adopts_existing_order_instead_of_duplicating(): void {
        $transfer = $this->confirmedTransfer();
        $marker = OrgaMaxTarget::MARKER_PREFIX . $transfer->payload_hash;

        $fake = FakePluginHttp::fake([
            // Ein früherer, unklarer Lauf hat den Auftrag bereits erzeugt.
            'https://api.orgamax.de/openapi/order?*' => FakePluginHttp::response([
                ['id' => 'om-order-77', 'reference' => $marker],
            ]),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Transferred, $transfer->status);

        $reference = ExternalReference::query()
            ->where('external_type', OrgaMaxTarget::EXT_TYPE_ORDER)
            ->firstOrFail();
        $this->assertSame('om-order-77', $reference->external_id);
        $this->assertTrue((bool) ($reference->payload['adopted_via_reconciliation'] ?? false));

        // KEIN schreibender Create — nur der lesende Scan.
        $fake->assertNotSent(fn(RequestInterface $r) => $r->getMethod() === 'POST');
    }

    public function test_second_execute_returns_existing_reference_without_new_order(): void {
        $transfer = $this->confirmedTransfer();

        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/order?*' => FakePluginHttp::response([]),
            'https://api.orgamax.de/openapi/order/*' => FakePluginHttp::response(['id' => 'om-order-1'], 201),
        ]);
        $this->post(route('finance.transfers.execute', $transfer));

        // Direkter Zweitaufruf des Targets (z. B. nach Fehlklassifikation):
        // liefert die bestehende Referenz, ohne die API zu berühren.
        $fake = FakePluginHttp::fake([]);
        $result = app(OrgaMaxTarget::class)->transfer($transfer->fresh());
        $this->assertSame('om-order-1', $result->externalReference?->external_id);
        $fake->assertNothingSent();
    }

    public function test_unmapped_customer_fails_with_inbox_hint(): void {
        ExternalReference::query()->delete();
        $transfer = $this->confirmedTransfer();

        FakePluginHttp::fake([
            'https://api.orgamax.de/openapi/order?*' => FakePluginHttp::response([]),
        ]);

        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $transfer->refresh();
        $this->assertSame(TransferStatus::Failed, $transfer->status);
        $this->assertStringContainsString('zugeordnet', (string) $transfer->failure_reason);

        // Quellen bleiben frei (retry-fähig).
        $this->assertSame(1, TimeEntry::query()->where('exported', false)->count());
    }

    public function test_billing_capability_must_be_enabled_and_led_by_orgamax(): void {
        $this->connection->forceFill([
            'capabilities' => ['billing' => ['enabled' => true, 'leader' => 'manual_review']],
        ])->save();
        $transfer = $this->confirmedTransfer();

        FakePluginHttp::fake([]);
        $this->post(route('finance.transfers.execute', $transfer))->assertRedirect();

        $this->assertSame(TransferStatus::Failed, $transfer->fresh()->status);
    }
}
