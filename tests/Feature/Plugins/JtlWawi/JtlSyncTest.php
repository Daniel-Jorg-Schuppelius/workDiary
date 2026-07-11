<?php
/*
 * Created on   : Sat Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : JtlSyncTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Plugins\JtlWawi;

use App\Models\{Article, ArticleVariant, ExternalArticleMapping, IntegrationInboxItem, JtlConnection, JtlStockSnapshot, JtlWarehouseMapping, Warehouse};
use App\Plugins\JtlWawi\Services\{JtlArticleImporter, JtlStockChangePoller, JtlWarehouseImporter};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * Feature 078, MVP-318/319/320: Lager-Projektion, Artikel-Matching
 * (SKU sicher, keine Schattenstammdaten, Inbox für Unklares) und
 * Bestands-Delta-Polling mit Checkpoint + Spiegelbestand.
 */
final class JtlSyncTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const BASE = 'https://wawi.example.test:5883/api/eazybusiness';

    private JtlConnection $connection;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->connection = JtlConnection::query()->create([
            'organization_id' => $this->organization->id,
            'mode' => JtlConnection::MODE_ON_PREMISE,
            'base_url' => self::BASE,
            'api_version' => '2.1',
            'allow_private_network' => true,
            'api_key' => 'KEY-TEST',
            'status' => JtlConnection::STATUS_ACTIVE,
        ]);
    }

    public function test_warehouse_import_projects_jtl_warehouses(): void {
        FakePluginHttp::fake([
            self::BASE . '/v2/warehouses*' => FakePluginHttp::response([
                'items' => [
                    ['id' => 'WH-1', 'name' => 'Hauptlager', 'code' => 'HL', 'isActive' => true, 'type' => ['name' => 'Standard']],
                    ['id' => 'WH-2', 'name' => 'Sperrlager', 'lockForAvailability' => true, 'isActive' => false],
                ],
                'hasNextPage' => false,
            ]),
        ]);

        $result = app(JtlWarehouseImporter::class)->import($this->connection);

        $this->assertSame(['seen' => 2, 'created' => 2, 'updated' => 0], $result);
        $this->assertDatabaseHas('jtl_warehouse_mappings', [
            'organization_id' => $this->organization->id,
            'jtl_warehouse_id' => 'WH-1',
            'name' => 'Hauptlager',
            'warehouse_type' => 'Standard',
        ]);
        $this->assertDatabaseHas('jtl_warehouse_mappings', [
            'jtl_warehouse_id' => 'WH-2',
            'jtl_is_active' => false,
            'lock_for_availability' => true,
        ]);

        // Erneuter Lauf aktualisiert statt zu duplizieren; Zuordnung bleibt.
        JtlWarehouseMapping::query()->where('jtl_warehouse_id', 'WH-1')->update(['warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id]);
        $again = app(JtlWarehouseImporter::class)->import($this->connection);
        $this->assertSame(2, $again['updated']);
        $this->assertNotNull(JtlWarehouseMapping::query()->where('jtl_warehouse_id', 'WH-1')->value('warehouse_id'));
    }

    public function test_article_import_links_by_sku_and_inboxes_unknown(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'number' => 'TS']);
        $variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'sku' => 'TS-ROT-S',
        ]);

        FakePluginHttp::fake([
            self::BASE . '/v2/items*' => FakePluginHttp::response([
                'items' => [
                    ['id' => 'JTL-PARENT', 'sKU' => 'TS', 'name' => 'T-Shirt', 'childItems' => [1, 2]],
                    ['id' => 'JTL-CHILD-1', 'sKU' => 'TS-ROT-S', 'name' => 'T-Shirt Rot S', 'parentItemId' => 'JTL-PARENT'],
                    ['id' => 'JTL-UNKNOWN', 'sKU' => 'GIBTS-NICHT', 'name' => 'Fremdartikel'],
                ],
                'hasNextPage' => false,
            ]),
        ]);

        $result = app(JtlArticleImporter::class)->import($this->connection);

        $this->assertSame(3, $result['seen']);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(1, $result['unmatched']);

        $this->assertDatabaseHas('external_article_mappings', [
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'JTL-CHILD-1',
            'article_variant_id' => $variant->id,
            'external_parent_id' => 'JTL-PARENT',
            'sync_status' => 'linked',
        ]);
        // Vaterartikel als Projektion auf den Hauptartikel, ohne Variante.
        $this->assertDatabaseHas('external_article_mappings', [
            'external_id' => 'JTL-PARENT',
            'article_id' => $article->id,
            'article_variant_id' => null,
        ]);
        // Kein Schattenstammsatz — der unbekannte Artikel landet in der Inbox.
        $this->assertSame(0, ArticleVariant::query()->where('sku', 'GIBTS-NICHT')->count());
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'JTL-UNKNOWN',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);

        $this->assertNotNull($this->connection->refresh()->article_checkpoint_at);
    }

    public function test_article_import_notes_contract_deviation_when_items_list_missing(): void {
        FakePluginHttp::fake([
            self::BASE . '/v2/items*' => FakePluginHttp::response(['errorCode' => 'NOT_FOUND'], 404),
        ]);

        $result = app(JtlArticleImporter::class)->import($this->connection);

        $this->assertTrue($result['skipped']);
        $this->assertNotEmpty($this->connection->refresh()->contract_notes);
        $this->assertNull($this->connection->article_checkpoint_at);
    }

    public function test_stock_poller_refreshes_snapshots_and_advances_checkpoint(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'SKU-1']);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);

        ExternalArticleMapping::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'ITEM-1',
            'article_id' => $article->id,
            'article_variant_id' => $variant->id,
        ]);
        JtlWarehouseMapping::query()->create([
            'organization_id' => $this->organization->id,
            'jtl_warehouse_id' => 'WH-1',
            'name' => 'Hauptlager',
            'warehouse_id' => $warehouse->id,
        ]);

        FakePluginHttp::fake([
            self::BASE . '/v2/stocks/changes*' => FakePluginHttp::response([
                'items' => [
                    ['itemId' => 'ITEM-1', 'warehouseId' => 'WH-1', 'quantity' => -2, 'changedDate' => now()->toIso8601String()],
                    ['itemId' => 'ITEM-FREMD', 'warehouseId' => 'WH-1', 'quantity' => 1, 'changedDate' => now()->toIso8601String()],
                ],
                'hasNextPage' => false,
            ]),
            self::BASE . '/v2/stocks*' => FakePluginHttp::response([
                'items' => [
                    [
                        'itemId' => 'ITEM-1',
                        'warehouseId' => 'WH-1',
                        'quantityTotal' => 10,
                        'quantityLockedForShipment' => 2,
                        'quantityLockedForAvailability' => 1,
                        'quantityInPickingLists' => 1,
                    ],
                ],
                'hasNextPage' => false,
            ]),
        ]);

        $result = app(JtlStockChangePoller::class)->poll($this->connection);

        $this->assertSame(2, $result['changes']);
        $this->assertSame(1, $result['refreshed']);
        $this->assertSame(1, $result['unknown_items']);
        $this->assertFalse($result['truncated']);

        $snapshot = JtlStockSnapshot::query()
            ->where('article_variant_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->firstOrFail();
        $this->assertSame('10.0000', $snapshot->quantity_total);
        // verfügbar = 10 − 2 − 1 − 1
        $this->assertSame('6.0000', $snapshot->quantity_available);
        $this->assertSame('3.0000', $snapshot->quantity_reserved);
        $this->assertSame('1.0000', $snapshot->quantity_blocked);

        $this->assertNotNull($this->connection->refresh()->stock_checkpoint_at);
        $this->assertDatabaseHas('integration_inbox_items', [
            'plugin_id' => 'jtl_wawi',
            'external_id' => 'ITEM-FREMD',
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
        ]);
    }
}
