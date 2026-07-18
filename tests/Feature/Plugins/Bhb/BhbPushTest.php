<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BhbPushTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Bhb;

use App\Enums\Finance\BillingMode;
use App\Enums\Integration\IntegrationOutboxStatus;
use App\Jobs\Integration\IntegrationOutboxDeliveryJob;
use App\Models\{Customer, ExternalReference, IntegrationOutboxEntry, Invoice, PluginSetting, User};
use App\Plugins\BuchhaltungsButler\BuchhaltungsButlerPlugin;
use App\Plugins\BuchhaltungsButler\Services\BhbOutboxDispatcher;
use App\Plugins\PluginHealth;
use App\Services\Integration\{IntegrationOutboxDispatcherResolver, IntegrationOutboxService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-432 (Phase 40): genau EIN Beleg-Push je ausgestellter lokaler
 * Rechnung — Observer enqueued nur beim Statusübergang, der Dispatcher ist
 * referenz-idempotent; externe Rechnungshoheit und deaktivierter Push sind
 * No-Ops; „API-Add-on fehlt" ist ein erklärter Blocked-State im Healthcheck.
 */
class BhbPushTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $accountant;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->accountant = User::factory()->buchhaltung()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->accountant);

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'number' => 'K-1001',
            'currency' => 'EUR',
            'created_by' => $this->accountant->id,
        ]);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BuchhaltungsButlerPlugin::ID,
            'enabled' => true,
            'settings' => ['api_client' => 'client-1', 'api_secret' => 'secret-1', 'api_key' => 'key-1'],
        ]);
    }

    private function draftInvoice(): Invoice {
        $invoice = Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'R2030-0001',
            'status' => Invoice::STATUS_DRAFT,
            'currency' => 'EUR',
            'tax_rate' => '19.00',
            'created_by' => $this->accountant->id,
        ]);
        $invoice->items()->create([
            'organization_id' => $this->organization->id,
            'description' => 'Beratung',
            'quantity' => '2.00',
            'unit' => 'Std.',
            'unit_price' => '100.00',
            'tax_rate' => '19.00',
            'position' => 1,
        ]);
        $invoice->recalculate();
        $invoice->save();

        return $invoice->fresh();
    }

    /** @return array<string, mixed> */
    private function stubs(): array {
        return [
            'https://app.buchhaltungsbutler.de/api/v1/receipts/add' => FakePluginHttp::response(['success' => true, 'id' => 4711]),
        ];
    }

    public function test_issuing_invoice_pushes_receipt_once_with_hash_proof(): void {
        $invoice = $this->draftInvoice();
        $fake = FakePluginHttp::fake($this->stubs());

        $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_on' => '2030-04-15']);

        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(BhbOutboxDispatcher::OP_RECEIPT_PUSH, $entry->operation);
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->status);

        $reference = ExternalReference::query()
            ->where('plugin_id', BuchhaltungsButlerPlugin::ID)
            ->where('external_type', BhbOutboxDispatcher::EXT_TYPE_RECEIPT)
            ->firstOrFail();
        $this->assertSame('4711', $reference->external_id);
        $this->assertNotEmpty($reference->payload['document_sha256'] ?? null);
        $this->assertSame('rechnung-R2030-0001.pdf', $reference->payload['filename'] ?? null);

        // Multipart-Upload mit Pflicht-Formfeld api_key, Datei und Metadaten.
        $fake->assertSent(function (RequestInterface $r): bool {
            if ($r->getMethod() !== 'POST' || ! str_ends_with((string) $r->getUri(), '/receipts/add')) {
                return false;
            }
            $body = (string) $r->getBody();

            return str_contains($body, 'name="api_key"')
                && str_contains($body, 'key-1')
                && str_contains($body, 'filename="rechnung-R2030-0001.pdf"')
                && str_contains($body, 'name="number"')
                && str_contains($body, 'R2030-0001');
        });

        // Folge-Save ohne Statuswechsel erzeugt keinen zweiten Eintrag.
        $invoice->fresh()->touch();
        $this->assertSame(1, IntegrationOutboxEntry::withoutGlobalScopes()->count());
    }

    public function test_draft_and_non_status_changes_do_not_enqueue(): void {
        $fake = FakePluginHttp::fake([]);
        $invoice = $this->draftInvoice();

        $invoice->update(['due_on' => '2030-05-01']);

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();
    }

    public function test_external_billing_mode_skips_push(): void {
        $this->customer->update(['billing_mode' => BillingMode::Lexoffice]);
        $fake = FakePluginHttp::fake([]);
        $invoice = $this->draftInvoice();

        $invoice->update(['status' => Invoice::STATUS_ISSUED]);

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();
    }

    public function test_disabled_push_setting_skips_enqueue(): void {
        PluginSetting::query()->firstOrFail()->update([
            'settings' => ['api_client' => 'client-1', 'api_secret' => 'secret-1', 'api_key' => 'key-1', 'push_enabled' => false],
        ]);
        $fake = FakePluginHttp::fake([]);
        $invoice = $this->draftInvoice();

        $invoice->update(['status' => Invoice::STATUS_ISSUED]);

        $this->assertSame(0, IntegrationOutboxEntry::withoutGlobalScopes()->count());
        $fake->assertNothingSent();
    }

    public function test_dispatch_with_existing_reference_uploads_nothing(): void {
        $invoice = $this->draftInvoice();

        // Asynchrones Fenster: Enqueue ohne Zustellung, Referenz existiert schon.
        Queue::fake();
        $invoice->update(['status' => Invoice::STATUS_ISSUED]);
        $entry = IntegrationOutboxEntry::withoutGlobalScopes()->firstOrFail();

        ExternalReference::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BuchhaltungsButlerPlugin::ID,
            'external_type' => BhbOutboxDispatcher::EXT_TYPE_RECEIPT,
            'referenceable_type' => $invoice->getMorphClass(),
            'referenceable_id' => $invoice->getKey(),
            'external_id' => '4711',
            'payload' => ['source' => 'buchhaltungsbutler'],
            'synced_at' => now(),
        ]);

        $fake = FakePluginHttp::fake([]);
        (new IntegrationOutboxDeliveryJob($entry->id))->handle(
            app(IntegrationOutboxService::class),
            app(IntegrationOutboxDispatcherResolver::class),
        );

        $fake->assertNothingSent();
        $this->assertSame(IntegrationOutboxStatus::Confirmed, $entry->refresh()->status);
        $this->assertSame(1, ExternalReference::query()->where('external_type', BhbOutboxDispatcher::EXT_TYPE_RECEIPT)->count());
    }

    public function test_missing_api_addon_maps_to_failing_blocked_state(): void {
        FakePluginHttp::fake([
            'https://app.buchhaltungsbutler.de/api/v1/receipts/get' => FakePluginHttp::response(['message' => 'API access not booked'], 403),
        ]);

        $health = app(BuchhaltungsButlerPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertSame('api_addon_missing', $health->code);
    }

    public function test_rejected_credentials_map_to_auth_failing(): void {
        FakePluginHttp::fake([
            'https://app.buchhaltungsbutler.de/api/v1/receipts/get' => FakePluginHttp::response(['message' => 'unauthorized'], 401),
        ]);

        $health = app(BuchhaltungsButlerPlugin::class)->healthCheck();

        $this->assertSame(PluginHealth::STATUS_FAILING, $health->status);
        $this->assertSame('auth', $health->code);
    }
}
