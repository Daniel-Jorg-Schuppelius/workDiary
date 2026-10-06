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

use App\Enums\Inventory\{StockLotStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, Warehouse, WarehouseBin};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use App\Services\Inventory\{InventoryLedger, LotService, LotSplitService, LotStockReader, PickList, PickListBuilder, ReservationService, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    /** Gesperrte Charge: bleibt im Bestand, steht aber weder als Charge noch als Rest ohne Charge zur Entnahme. */
    public function test_blocked_lot_is_not_suggested_and_its_stock_stays_in_the_warehouse(): void {
        $ledger = app(InventoryLedger::class);
        $lots = app(LotService::class);
        $variant = $this->makeVariant('LOT-BLOCK');
        $held = $lots->register($variant, 'L-HELD', '2026-05-01');
        $free = $lots->register($variant, 'L-FREE', '2026-06-01');
        $lots->receiveIntoLot($variant, $this->warehouse, '5', '2', $held);
        $lots->receiveIntoLot($variant, $this->warehouse, '3', '2', $free);
        $request = [['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '6.0000']];

        // Gegenprobe vor der Sperre: FEFO nimmt die früher verfallende Charge zuerst.
        $this->assertSame(
            [['L-HELD', '5.0000', '5.0000', false], ['L-FREE', '1.0000', '3.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );

        $lots->block($held, 'Reklamation des Lieferanten', $this->admin);

        $this->assertSame(
            [['L-FREE', '3.0000', '3.0000', false], [null, '3.0000', '0.0000', true]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );
        // Der Bestand ist nicht weg: Lagerbuch und Chargenbestand stehen wie zuvor, die Charge ist gesperrt.
        $this->assertSame('8.0000', $ledger->balance($variant, $this->warehouse, StockState::Physical));
        $this->assertSame('5.0000', app(LotStockReader::class)->balanceOf($held));
        $this->assertSame(StockLotStatus::Blocked, $held->fresh()?->status);

        // Freigegeben steht sie wieder vorn.
        $lots->unblock($held, 'Prüfung ohne Befund', $this->admin);
        $this->assertSame('L-HELD', app(PickListBuilder::class)->fromLines($request)->lines[0]->lot?->lot_no);
    }

    /** Mit Lagerplätzen zählt der gesperrte Bestand am Platz nicht mit; die Lücke erscheint als Fehlmenge. */
    public function test_blocked_lot_stock_does_not_count_at_its_bin(): void {
        $ledger = app(InventoryLedger::class);
        $variant = $this->makeVariant('LOT-BIN');
        $near = $this->makeBin('R1', 1);
        $far = $this->makeBin('R2', 2);
        $held = $this->makeLot($variant, 'L-HELD', '2026-05-01');
        $free = $this->makeLot($variant, 'L-FREE', '2026-06-01');
        $other = $this->makeLot($variant, 'L-OTHER', '2026-07-01');
        $this->receive($variant, $held, '5', $near);
        $this->receive($variant, $free, '3', $near);
        $this->receive($variant, $other, '2', $far);
        app(LotService::class)->block($held, 'Reklamation', $this->admin);

        $list = app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '7.0000']]);

        $this->assertSame(
            [['R1', 'L-FREE', '3.0000', false], ['R2', 'L-OTHER', '2.0000', false], [null, null, '2.0000', true]],
            array_map(fn ($line): array => [$line->bin?->code, $line->lot?->lot_no, $line->qty, $line->isShort()], $list->lines),
        );
        $this->assertSame('8.0000', $ledger->balance($variant, $this->warehouse, StockState::Physical, bin: $near));

        // Fester Platz: der gesperrte Rest erscheint dort als Fehlmenge, nicht als Entnahme ohne Charge.
        $fixed = app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '5.0000', 'bin' => $near]]);
        $this->assertSame(
            [['L-FREE', '3.0000', '3.0000', false], [null, '2.0000', '0.0000', true]],
            $this->lotLines($fixed),
        );
    }

    /**
     * Altbestand: frühere Abgänge trugen keine Charge, der gebuchte Saldo einer längst verbrauchten Charge
     * bleibt stehen. Wird sie gesperrt, darf das den Bestand der aktiven Chargen nicht mit stilllegen.
     */
    public function test_blocking_a_used_up_lot_does_not_withhold_the_stock_of_active_lots(): void {
        $ledger = app(InventoryLedger::class);
        $variant = $this->makeVariant('LOT-STALE');
        $bin = $this->makeBin('R1', 1);
        $old = $this->makeLot($variant, 'L-OLD', '2026-05-01');
        $new = $this->makeLot($variant, 'L-NEW', '2026-08-01');
        $this->receive($variant, $old, '5', $bin);
        $ledger->post(new StockPosting($variant, $this->warehouse, StockState::Physical, '-5.0000', StockMovementType::Issue, bin: $bin));
        $this->receive($variant, $new, '4', $bin);
        app(LotService::class)->block($old, 'Rückruf', $this->admin);

        $list = app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '4.0000']]);

        $this->assertSame(
            [['R1', 'L-NEW', '4.0000', '4.0000', false]],
            array_map(fn ($line): array => [$line->bin?->code, $line->lot?->lot_no, $line->qty, $line->available, $line->isShort()], $list->lines),
        );
    }

    /** Der Abgang trägt seine Charge: eine verbrauchte Charge schlägt der Pickzettel nicht mehr vor. */
    public function test_consumed_lot_is_no_longer_suggested(): void {
        $ledger = app(InventoryLedger::class);
        $variant = $this->makeVariant('LOT-USED');
        $bin = $this->makeBin('R1', 1);
        $early = $this->makeLot($variant, 'L-EARLY', '2026-05-01');
        $late = $this->makeLot($variant, 'L-LATE', '2026-08-01');
        $this->receive($variant, $early, '3', $bin);
        $this->receive($variant, $late, '4', $bin);
        $request = [['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '4.0000']];

        // Der Abgang folgt derselben FEFO-Folge wie der Vorschlag: erst L-EARLY, dann L-LATE.
        $this->assertSame(
            [['L-EARLY', '3.0000', '3.0000', false], ['L-LATE', '1.0000', '4.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );
        $issue = $ledger->issue($variant, $this->warehouse, '4', bin: $bin);
        $this->assertSame([$early->id, $late->id], array_map(fn (StockMovement $m): ?int => $m->stock_lot_id, $issue->movements));

        $this->assertSame(
            [['L-LATE', '3.0000', '3.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '3.0000']])),
        );
    }

    /** Teilen bucht um: die abgeteilte Charge steht mit ihrem eigenen MHD auf dem Pickzettel. */
    public function test_split_lot_appears_on_the_pick_list(): void {
        $lots = app(LotService::class);
        $variant = $this->makeVariant('LOT-SPLIT');
        $source = $lots->register($variant, 'L-SOURCE', '2026-09-01');
        $lots->receiveIntoLot($variant, $this->warehouse, '10', '2', $source);

        app(LotSplitService::class)->split($source, '4', 'L-PART', '2026-05-01');

        $this->assertSame(
            [['L-PART', '4.0000', '4.0000', false], ['L-SOURCE', '2.0000', '6.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '6.0000']])),
        );
    }

    /** Zusammengeführt: die Quellcharge erscheint nicht mehr, ihr Bestand ist über die Zielcharge pickbar. */
    public function test_merged_lot_is_picked_through_its_target_lot(): void {
        $lots = app(LotService::class);
        $variant = $this->makeVariant('LOT-MERGE');
        $target = $lots->register($variant, 'L-TARGET', '2026-09-01');
        $source = $lots->register($variant, 'L-SOURCE', '2026-06-01');
        $lots->receiveIntoLot($variant, $this->warehouse, '6', '2', $target);
        $lots->receiveIntoLot($variant, $this->warehouse, '5', '3', $source);
        $request = [['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '11.0000']];

        $this->assertSame(
            [['L-SOURCE', '5.0000', '5.0000', false], ['L-TARGET', '6.0000', '6.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );

        app(LotSplitService::class)->merge($source, $target);

        $this->assertSame(
            [['L-TARGET', '11.0000', '11.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );
    }

    /** Altbestand: früher zusammengeführt, die Bewegungen hängen noch an der Quellcharge. */
    public function test_legacy_merged_lot_counts_for_its_target_or_stays_pickable_without_lot(): void {
        $variant = $this->makeVariant('LOT-LEGACY');
        $target = $this->makeLot($variant, 'L-TARGET', '2026-09-01');
        $source = $this->makeLot($variant, 'L-SOURCE', '2026-06-01');
        $this->receive($variant, $target, '6');
        $this->receive($variant, $source, '5');
        $request = [['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '11.0000']];

        // Zielcharge bekannt (von der Migration nachgetragen): der Bestand zählt bei ihr.
        DB::table('stock_lots')->where('id', $source->id)->update(['status' => 'merged', 'merged_into_lot_id' => $target->id]);
        $this->assertSame(
            [['L-TARGET', '11.0000', '11.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );

        // Zielcharge unbekannt: der Bestand verschwindet nicht, er bleibt ohne Charge pickbar.
        DB::table('stock_lots')->where('id', $source->id)->update(['merged_into_lot_id' => null]);
        $this->assertSame(
            [['L-TARGET', '6.0000', '6.0000', false], [null, '5.0000', '5.0000', false]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );

        // Zielcharge inzwischen gesperrt: der zugerechnete Bestand ist es auch.
        DB::table('stock_lots')->where('id', $source->id)->update(['merged_into_lot_id' => $target->id]);
        DB::table('stock_lots')->where('id', $target->id)->update(['status' => 'blocked']);
        $this->assertSame(
            [[null, '11.0000', '0.0000', true]],
            $this->lotLines(app(PickListBuilder::class)->fromLines($request)),
        );
    }

    /** Die Migration trägt die Zielcharge aus den Bewertungsschichten nach — nur wenn sie eindeutig ist. */
    public function test_migration_backfills_the_target_of_legacy_merged_lots(): void {
        $lots = app(LotService::class);
        $variant = $this->makeVariant('LOT-BACKFILL');
        $target = $lots->register($variant, 'L-TARGET');
        $source = $lots->register($variant, 'L-SOURCE');
        $split = $lots->register($variant, 'L-SPLIT');
        $unclear = $lots->register($variant, 'L-UNCLEAR');
        $lots->receiveIntoLot($variant, $this->warehouse, '6', '2', $target);
        $lots->receiveIntoLot($variant, $this->warehouse, '5', '3', $source);
        $lots->receiveIntoLot($variant, $this->warehouse, '4', '3', $unclear);
        $lots->receiveIntoLot($variant, $this->warehouse, '4', '3', $unclear);
        // Vor dem Zusammenführen abgeteilt: die spätere Schicht zeigt auf eine dritte Charge und zählt nicht.
        app(LotSplitService::class)->split($source, '2', 'L-PART');

        // Früheres Zusammenführen: nur die Schichten wurden umgehängt.
        DB::table('stock_valuation_layers')->where('stock_lot_id', $source->id)->update(['stock_lot_id' => $target->id]);
        $unclearLayers = DB::table('stock_valuation_layers')->where('stock_lot_id', $unclear->id)->orderBy('id')->pluck('id');
        DB::table('stock_valuation_layers')->where('id', $unclearLayers[0])->update(['stock_lot_id' => $target->id]);
        DB::table('stock_valuation_layers')->where('id', $unclearLayers[1])->update(['stock_lot_id' => $split->id]);
        DB::table('stock_lots')->whereIn('id', [$source->id, $unclear->id])->update(['status' => 'merged']);
        $journal = StockMovement::query()->orderBy('id')->get()->map->getAttributes()->all();

        (require database_path('migrations/2027_03_10_100000_add_block_and_merge_fields_to_stock_lots.php'))->up();

        $this->assertSame($target->id, $source->fresh()?->merged_into_lot_id);
        $this->assertNull($unclear->fresh()?->merged_into_lot_id);
        $this->assertSame($journal, StockMovement::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    /** Die Reihenfolge der übrigen Chargen bleibt FEFO: frühestes MHD zuerst, ohne MHD zuletzt, dann Chargennummer. */
    public function test_fefo_order_of_the_remaining_lots_is_unchanged(): void {
        $variant = $this->makeVariant('LOT-ORDER');
        foreach ([['L-NONE', null], ['L-LATE', '2027-01-01'], ['L-HELD', '2026-01-01'], ['L-B', '2026-06-01'], ['L-A', '2026-06-01'], ['L-GONE', '2026-02-01']] as [$no, $bestBefore]) {
            $this->receive($variant, $this->makeLot($variant, $no, $bestBefore), '1');
        }
        $lots = StockLot::query()->where('article_variant_id', $variant->id)->get()->keyBy('lot_no');
        app(LotService::class)->block($lots['L-HELD'], 'Reklamation', $this->admin);
        app(LotSplitService::class)->merge($lots['L-GONE'], $lots['L-LATE']);

        $list = app(PickListBuilder::class)->fromLines([['variant' => $variant, 'warehouse' => $this->warehouse, 'qty' => '5.0000']]);

        $this->assertSame(
            [['L-A', '1.0000'], ['L-B', '1.0000'], ['L-LATE', '2.0000'], ['L-NONE', '1.0000']],
            array_map(fn ($line): array => [$line->lot?->lot_no, $line->qty], $list->lines),
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

    /** @return list<array{0: string|null, 1: string, 2: string, 3: bool}> Charge, Menge, verfügbar, Fehlmenge */
    private function lotLines(PickList $list): array {
        return array_map(fn ($line): array => [$line->lot?->lot_no, $line->qty, $line->available, $line->isShort()], $list->lines);
    }

    private function makeLot(ArticleVariant $variant, string $lotNo, ?string $bestBefore = null): StockLot {
        return StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $variant->id, 'lot_no' => $lotNo, 'best_before' => $bestBefore]);
    }

    private function receive(ArticleVariant $variant, StockLot $lot, string $qty, ?WarehouseBin $bin = null): void {
        app(InventoryLedger::class)->post(new StockPosting($variant, $this->warehouse, StockState::Physical, $qty, StockMovementType::Receipt, stockLotId: $lot->id, bin: $bin));
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
