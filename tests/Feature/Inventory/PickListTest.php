<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickListTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockMovementType, StockState};
use App\Models\{Article, ArticleVariant, ManufacturingOrder, StockLot, User, Warehouse, WarehouseBin};
use App\Services\Inventory\{InventoryLedger, PickListBuilder, ReservationService, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kommissionierliste (Feature 048, MVP-706): Positionen aus aktiven
 * Reservierungen einer Quelle, Verteilung über Plätze (sort_order) und
 * Chargen (FEFO), Sortierung, Fehlmenge, HTML- und PDF-Route.
 */
final class PickListTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Halle']);
    }

    public function test_pick_list_from_reservations_allocates_bins_and_sorts(): void {
        $ledger = app(InventoryLedger::class);
        $binA = $this->makeBin('A', 2);
        $binB = $this->makeBin('B', 1);
        $v1 = $this->makeVariant('X-2');
        $v2 = $this->makeVariant('X-1');
        $ledger->receipt($v1, $this->warehouse, '4', bin: $binA);
        $ledger->receipt($v1, $this->warehouse, '4', bin: $binB);
        $ledger->receipt($v2, $this->warehouse, '5', bin: $binA);

        $order = $this->makeOrder();
        $reservations = app(ReservationService::class);
        $reservations->reserve($v1, $this->warehouse, '6', source: $order);              // ohne Platz → B (sort 1) 4, dann A 2
        $reservations->reserve($v2, $this->warehouse, '3', source: $order, bin: $binA);  // fester Platz A
        $this->assertDatabaseHas('stock_reservations', ['article_variant_id' => $v2->id, 'bin_id' => $binA->id]);

        $list = app(PickListBuilder::class)->forSource($order);

        $this->assertCount(3, $list->lines);
        $this->assertSame(
            [['B', 'X-2', '4.0000'], ['A', 'X-1', '3.0000'], ['A', 'X-2', '2.0000']],
            array_map(fn ($line): array => [$line->bin?->code, $line->sku(), $line->qty], $list->lines),
        );
        // Verfügbar am Platz: B trägt 4 physisch, die Reservierung ohne Platz mindert B nicht.
        $this->assertSame('4.0000', $list->lines[0]->available);
        $this->assertFalse($list->lines[0]->isShort());
        $this->assertStringContainsString($order->number, (string) $list->sourceLabel());
    }

    public function test_pick_list_splits_lots_fefo(): void {
        $ledger = app(InventoryLedger::class);
        $variant = $this->makeVariant('LOT-ART');
        $late = StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $variant->id, 'lot_no' => 'L-LATE', 'best_before' => '2027-01-01']);
        $early = StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $variant->id, 'lot_no' => 'L-EARLY', 'best_before' => '2026-06-01']);
        $ledger->post(new StockPosting($variant, $this->warehouse, StockState::Physical, '3.0000', StockMovementType::Receipt, stockLotId: $late->id));
        $ledger->post(new StockPosting($variant, $this->warehouse, StockState::Physical, '2.0000', StockMovementType::Receipt, stockLotId: $early->id));

        $list = app(PickListBuilder::class)->fromLines([
            ['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '4'],
        ]);

        $this->assertSame(
            [['L-EARLY', '2.0000', '2.0000'], ['L-LATE', '2.0000', '3.0000']],
            array_map(fn ($line): array => [$line->lot?->lot_no, $line->qty, $line->available], $list->lines),
        );
    }

    public function test_pick_list_marks_shortfall(): void {
        $variant = $this->makeVariant('EMPTY');
        $list = app(PickListBuilder::class)->fromLines([
            ['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '5'],
        ]);

        $this->assertCount(1, $list->lines);
        $this->assertNull($list->lines[0]->bin);
        $this->assertSame('0.0000', $list->lines[0]->available);
        $this->assertTrue($list->lines[0]->isShort());
    }

    public function test_pick_list_routes_render_html_and_pdf(): void {
        $variant = $this->makeVariant('SKU-PICK');
        $bin = $this->makeBin('R-07', 1);
        app(InventoryLedger::class)->receipt($variant, $this->warehouse, '9', bin: $bin);
        $order = $this->makeOrder();
        app(ReservationService::class)->reserve($variant, $this->warehouse, '2', source: $order);

        $params = ['source' => 'manufacturing-order', 'sqid' => $order->sqid];
        $this->actingAs($this->admin)->get(route('inventory.pick-lists.show', $params))
            ->assertOk()->assertSee('SKU-PICK')->assertSee('R-07')->assertSee('2.0000');

        $this->actingAs($this->admin)->get(route('inventory.pick-lists.pdf', $params))
            ->assertOk()->assertHeader('content-type', 'application/pdf');

        // Unbekannte Quelle → 404; ohne inventory.viewAny → 403.
        $this->actingAs($this->admin)->get(route('inventory.pick-lists.show', ['source' => 'unknown-thing', 'sqid' => $order->sqid]))->assertNotFound();
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($stranger)->get(route('inventory.pick-lists.show', $params))->assertForbidden();
    }

    public function test_empty_pick_list_renders_empty_state(): void {
        $order = $this->makeOrder();

        $this->actingAs($this->admin)
            ->get(route('inventory.pick-lists.show', ['source' => 'manufacturing-order', 'sqid' => $order->sqid]))
            ->assertOk()->assertSee(__('inventory.empty.pick_list'));
    }

    private function makeOrder(): ManufacturingOrder {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'number' => 'FA-706',
        ]);
    }

    private function makeBin(string $code, int $sortOrder): WarehouseBin {
        return WarehouseBin::factory()->create([
            'organization_id' => $this->organization->id,
            'warehouse_id' => $this->warehouse->id,
            'code' => $code,
            'sort_order' => $sortOrder,
        ]);
    }

    private function makeVariant(string $sku): ArticleVariant {
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);

        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'option_signature' => 'default',
            'sku' => $sku,
        ]);
    }
}
