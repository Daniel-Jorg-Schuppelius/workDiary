<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BillbeeInventoryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\Billbee;

use App\Jobs\Integration\InventoryOutboxDeliveryJob;
use App\Models\{Article, ArticleVariant, InventoryOutboxEntry, PluginSetting, Warehouse};
use App\Plugins\Billbee\BillbeePlugin;
use App\Plugins\Billbee\Services\{BillbeeInventoryProvider, BillbeeStockDispatcher};
use App\Services\Inventory\{InventoryLedger, InventoryProviderResolver};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * MVP-434 (Phase 40): Bestandsrückkanal nach Billbee — lokale Bewegungen
 * landen im External-Mode als Outbox-Einträge und werden als ABSOLUTE
 * Stock-Updates (NewQuantity je SKU) zugestellt; Wiederholungen setzen
 * denselben Zielbestand (natürliche Idempotenz); ohne SKU-Mapping entsteht
 * ein klarer Fehler statt stiller Drift.
 */
class BillbeeInventoryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization([
            'settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'billbee'],
        ]);

        PluginSetting::create([
            'organization_id' => $this->organization->id,
            'plugin_id' => BillbeePlugin::ID,
            'enabled' => true,
            'settings' => ['api_key' => 'bb-key', 'username' => 'shopper', 'api_password' => 'bb-pass'],
        ]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'SKU-B1',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    public function test_resolver_returns_billbee_provider_and_local_reads(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);

        $provider = app(InventoryProviderResolver::class)->providerFor($this->organization);
        $this->assertInstanceOf(BillbeeInventoryProvider::class, $provider);

        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '5', idempotencyKey: 'bb-read-1');

        // Lesequelle ist das lokale Journal (Billbee ist Verteiler, kein Lagerführer).
        $fake = FakePluginHttp::fake();
        $this->assertSame(5.0, (float) $provider->available($this->variant, $this->warehouse));
        $fake->assertNothingSent();
    }

    public function test_ledger_mirror_enqueues_billbee_outbox_entry(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);

        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '5', idempotencyKey: 'bb-mv-1');

        $this->assertDatabaseHas('inventory_outbox', [
            'organization_id' => $this->organization->id,
            'plugin_id' => BillbeePlugin::ID,
            'idempotency_key' => 'bb-mv-1',
        ]);
    }

    public function test_dispatcher_sets_absolute_quantity_and_is_repeat_safe(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '5', idempotencyKey: 'bb-mv-2');
        $entry = InventoryOutboxEntry::query()->withoutGlobalScopes()
            ->where('idempotency_key', 'bb-mv-2')
            ->firstOrFail();

        $fake = FakePluginHttp::fake([
            'https://app.billbee.io/api/v1/products/updatestock' => FakePluginHttp::response(['Data' => null, 'ErrorMessage' => null]),
        ]);

        $dispatcher = new BillbeeStockDispatcher();
        $this->assertTrue($dispatcher->dispatch($entry));
        // Timeout-Replay: erneute Zustellung setzt DENSELBEN Absolutwert.
        $this->assertTrue($dispatcher->dispatch($entry));

        $fake->assertSent(function (RequestInterface $r): bool {
            if ($r->getMethod() !== 'POST' || ! str_ends_with((string) $r->getUri(), '/products/updatestock')) {
                return false;
            }
            $body = json_decode((string) $r->getBody(), true);

            return ($body['Sku'] ?? '') === 'SKU-B1'
                && (float) ($body['NewQuantity'] ?? -1) === 5.0
                && str_starts_with((string) ($body['Reason'] ?? ''), 'workdiary:bb-mv-2');
        });
        $fake->assertSentCount(2);
    }

    public function test_missing_sku_fails_loud_instead_of_silent_drift(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        $this->variant->forceFill(['sku' => null])->save();
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '3', idempotencyKey: 'bb-mv-3');
        $entry = InventoryOutboxEntry::query()->withoutGlobalScopes()
            ->where('idempotency_key', 'bb-mv-3')
            ->firstOrFail();

        $fake = FakePluginHttp::fake([]);

        $this->expectException(RuntimeException::class);
        try {
            (new BillbeeStockDispatcher())->dispatch($entry);
        } finally {
            $fake->assertNothingSent();
        }
    }
}
