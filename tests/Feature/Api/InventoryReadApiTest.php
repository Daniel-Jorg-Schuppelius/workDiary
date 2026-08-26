<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InventoryReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\{Article, ArticleVariant, Organization, User, Warehouse, WarehouseBin};
use App\Services\Inventory\InventoryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Bestände je Lager/Variante/Lagerplatz. */
final class InventoryReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['articles:read']);

        $this->getJson(route('api.inventory.index'))->assertForbidden();
        $this->getJson(route('api.inventory.warehouses'))->assertForbidden();
    }

    public function test_balances_per_warehouse_variant_and_bin(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kabel']);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'KAB-1', 'option_signature' => 'kab-1']);
        $other = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'KAB-2', 'option_signature' => 'kab-2']);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Hauptlager']);
        $second = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Fahrzeuglager']);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $warehouse->id, 'code' => 'A-01']);

        $ledger = app(InventoryLedger::class);
        $ledger->receipt($variant, $warehouse, '5', bin: $bin);
        $ledger->receipt($variant, $warehouse, '2');
        $ledger->reserve($variant, $warehouse, '1');
        $ledger->receipt($other, $second, '3');

        Sanctum::actingAs($this->admin, ['inventory:read']);

        $all = $this->getJson(route('api.inventory.index'))->assertOk();
        $this->assertSame(2, $all->json('meta.total'));

        $filtered = $this->getJson(route('api.inventory.index', ['warehouse' => $warehouse->sqid]))->assertOk();
        $this->assertCount(1, $filtered->json('data'));
        $row = $filtered->json('data.0');
        $this->assertSame($warehouse->sqid, $row['warehouse']['id']);
        $this->assertSame($variant->sqid, $row['variant']['id']);
        $this->assertSame($article->sqid, $row['variant']['article']['id']);
        $this->assertSame('7.0000', $row['balances']['physical']);
        $this->assertSame('1.0000', $row['balances']['reserved']);
        $this->assertSame('6.0000', $row['available']);
        $this->assertSame([['id' => $bin->sqid, 'code' => 'A-01', 'name' => $bin->name, 'qty' => '5.0000']], $row['bins']);

        $byVariant = $this->getJson(route('api.inventory.index', ['variant' => $other->sqid]))->assertOk();
        $this->assertCount(1, $byVariant->json('data'));
        $this->assertSame($second->sqid, $byVariant->json('data.0.warehouse.id'));

        $this->getJson(route('api.inventory.index', ['warehouse' => 'unbekannt']))->assertNotFound();
    }

    public function test_pagination_and_warehouse_catalog(): void {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $warehouse->id, 'code' => 'B-02']);
        $ledger = app(InventoryLedger::class);
        foreach (range(1, 3) as $i) {
            $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'sku' => 'V-' . $i, 'option_signature' => 'v-' . $i]);
            $ledger->receipt($variant, $warehouse, (string) $i);
        }
        Sanctum::actingAs($this->admin, ['inventory:read']);

        $page = $this->getJson(route('api.inventory.index', ['per_page' => 2, 'page' => 2]))->assertOk();
        $this->assertCount(1, $page->json('data'));
        $this->assertSame(3, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.last_page'));

        $catalog = $this->getJson(route('api.inventory.warehouses'))->assertOk();
        $this->assertSame($warehouse->sqid, $catalog->json('data.0.id'));
        $this->assertSame('B-02', $catalog->json('data.0.bins.0.code'));
    }

    public function test_foreign_organization_is_invisible(): void {
        $other = Organization::factory()->create();
        $foreignWarehouse = Warehouse::factory()->create(['organization_id' => $other->id]);
        Sanctum::actingAs($this->admin, ['inventory:read']);

        $this->getJson(route('api.inventory.index', ['warehouse' => $foreignWarehouse->sqid]))->assertNotFound();
        $this->assertCount(0, $this->getJson(route('api.inventory.warehouses'))->json('data'));
    }
}
