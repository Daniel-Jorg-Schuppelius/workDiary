<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlDispatchTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\JtlWawi;

use App\Enums\Inventory\StockState;
use App\Jobs\Integration\InventoryOutboxDeliveryJob;
use App\Models\{Article, ArticleVariant, ExternalArticleMapping, JtlConnection, JtlStockSnapshot, JtlWarehouseMapping, Warehouse};
use App\Plugins\JtlWawi\Services\{JtlWawiInventoryProvider, JtlWawiOutboxDispatcher};
use App\Services\Inventory\{InventoryLedger, InventoryOutboxService, InventoryProviderResolver, ReadOnlyInventoryProvider};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 078, MVP-319/321: Provider-Auflösung je Modus, zentraler
 * Outbox-Spiegel im Ledger (inkl. Kompensations-Ausnahme) und der
 * Dispatcher mit Idempotenz-Vorprüfung im Änderungsjournal — höchstens
 * EINE JTL-Buchung je freigegebener Bewegung.
 */
final class JtlDispatchTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://wawi.example.test:5883/api/eazybusiness';

    private ArticleVariant $variant;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization([
            'settings' => ['inventory_mode' => 'external', 'inventory_plugin_id' => 'jtl_wawi'],
        ]);

        JtlConnection::query()->create([
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.0',
            'allow_private_network' => true,
            'api_key' => 'KEY-TEST',
            'status' => JtlConnection::STATUS_ACTIVE,
        ]);

        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'SKU-D1',
        ]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        ExternalArticleMapping::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'ITEM-D1',
            'article_id' => $article->id,
            'article_variant_id' => $this->variant->id,
        ]);
        JtlWarehouseMapping::query()->create([
            'organization_id' => $this->organization->id,
            'jtl_warehouse_id' => 'WH-D1',
            'name' => 'Hauptlager',
            'warehouse_id' => $this->warehouse->id,
        ]);
    }

    public function test_resolver_returns_jtl_provider_for_external_and_readonly(): void {
        $resolver = app(InventoryProviderResolver::class);

        $this->assertInstanceOf(JtlWawiInventoryProvider::class, $resolver->providerFor($this->organization));

        $this->organization->forceFill([
            'settings' => ['inventory_mode' => 'read_only', 'inventory_plugin_id' => 'jtl_wawi'],
        ])->save();
        $this->assertInstanceOf(ReadOnlyInventoryProvider::class, $resolver->providerFor($this->organization->refresh()));
    }

    public function test_provider_reads_available_from_fresh_snapshot_without_http(): void {
        JtlStockSnapshot::query()->create([
            'organization_id' => $this->organization->id,
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_total' => '10.0000',
            'quantity_available' => '6.0000',
            'quantity_reserved' => '3.0000',
            'quantity_blocked' => '1.0000',
            'fetched_at' => now(),
        ]);
        $fake = FakePluginHttp::fake();

        $provider = app(InventoryProviderResolver::class)->providerFor($this->organization);

        $this->assertSame('6.0000', $provider->available($this->variant, $this->warehouse));
        $this->assertSame('10.0000', $provider->balance($this->variant, $this->warehouse, StockState::Physical));
        $fake->assertNothingSent();
    }

    public function test_ledger_mirrors_physical_movements_but_never_compensations(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        $ledger = app(InventoryLedger::class);

        $ledger->receipt($this->variant, $this->warehouse, '5', idempotencyKey: 'mv-mirror-1');
        $this->assertDatabaseHas('inventory_outbox', [
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl_wawi',
            'idempotency_key' => 'mv-mirror-1',
        ]);

        // Kompensations-Gegenbuchung darf NIE zurückgespiegelt werden.
        $ledger->correction($this->variant, $this->warehouse, StockState::Physical, '-5', idempotencyKey: 'compensate:42');
        $this->assertDatabaseMissing('inventory_outbox', ['idempotency_key' => 'compensate:42']);

        // read_only liest nur — kein Outbox-Eintrag.
        $this->organization->forceFill([
            'settings' => ['inventory_mode' => 'read_only', 'inventory_plugin_id' => 'jtl_wawi'],
        ])->save();
        $ledger->receipt($this->variant, $this->warehouse, '2', idempotencyKey: 'mv-mirror-2');
        $this->assertDatabaseMissing('inventory_outbox', ['idempotency_key' => 'mv-mirror-2']);
    }

    public function test_dispatcher_posts_delta_once_with_source_marker(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        $entry = app(InventoryOutboxService::class)->enqueue(
            (int) $this->organization->id,
            'jtl_wawi',
            'issue',
            $this->payload('-2.0000'),
            'mv-once-1',
        );

        $fake = FakePluginHttp::fake([
            self::BASE . '/v2/stocks/changes*' => FakePluginHttp::response(['items' => [], 'hasNextPage' => false]),
            self::BASE . '/v2/stocks' => FakePluginHttp::response(['itemId' => 'ITEM-D1'], 201),
        ]);

        $this->assertTrue(app(JtlWawiOutboxDispatcher::class)->dispatch($entry));

        $fake->assertSent(static function (RequestInterface $request): bool {
            if ($request->getMethod() !== 'POST' || ! str_ends_with((string) $request->getUri(), '/v2/stocks')) {
                return false;
            }
            $body = json_decode((string) $request->getBody(), true);

            return ($body['comment'] ?? '') === 'workdiary:mv-once-1'
                && ($body['warehouseId'] ?? '') === 'WH-D1'
                && ($body['itemId'] ?? '') === 'ITEM-D1'
                && (float) ($body['quantity'] ?? 0) === -2.0;
        });
    }

    public function test_dispatcher_skips_post_when_journal_already_contains_marker(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        $entry = app(InventoryOutboxService::class)->enqueue(
            (int) $this->organization->id,
            'jtl_wawi',
            'issue',
            $this->payload('-2.0000'),
            'mv-replay-1',
        );

        $fake = FakePluginHttp::fake([
            self::BASE . '/v2/stocks/changes*' => FakePluginHttp::response([
                'items' => [['itemId' => 'ITEM-D1', 'warehouseId' => 'WH-D1', 'quantity' => -2, 'comment' => 'workdiary:mv-replay-1']],
                'hasNextPage' => false,
            ]),
        ]);

        // Timeout-Replay: bereits verbucht ⇒ bestätigen ohne zweite Buchung.
        $this->assertTrue(app(JtlWawiOutboxDispatcher::class)->dispatch($entry));

        $fake->assertNotSent(static fn (RequestInterface $request): bool => $request->getMethod() === 'POST'
            && str_ends_with((string) $request->getUri(), '/v2/stocks'));
    }

    public function test_dispatcher_treats_reservations_as_external_noop(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        $payload = $this->payload('3.0000');
        $payload['stock_state'] = StockState::Reserved->value;
        $payload['movement_type'] = 'reserve';
        $entry = app(InventoryOutboxService::class)->enqueue(
            (int) $this->organization->id,
            'jtl_wawi',
            'reserve',
            $payload,
            'mv-reserve-1',
        );

        $fake = FakePluginHttp::fake();

        $this->assertTrue(app(JtlWawiOutboxDispatcher::class)->dispatch($entry));
        $fake->assertNothingSent();
    }

    public function test_dispatcher_fails_loud_when_mapping_is_missing(): void {
        Bus::fake([InventoryOutboxDeliveryJob::class]);
        ExternalArticleMapping::query()->delete();
        $entry = app(InventoryOutboxService::class)->enqueue(
            (int) $this->organization->id,
            'jtl_wawi',
            'issue',
            $this->payload('-1.0000'),
            'mv-unmapped-1',
        );

        FakePluginHttp::fake();

        $this->expectException(\RuntimeException::class);
        app(JtlWawiOutboxDispatcher::class)->dispatch($entry);
    }

    /** @return array<string, mixed> */
    private function payload(string $qty): array {
        return [
            'article_variant_id' => $this->variant->id,
            'warehouse_id' => $this->warehouse->id,
            'stock_state' => StockState::Physical->value,
            'movement_type' => 'issue',
            'qty_base' => $qty,
            'occurred_at' => now()->toIso8601String(),
            'stock_movement_id' => null,
            'stock_serial_id' => null,
        ];
    }
}
