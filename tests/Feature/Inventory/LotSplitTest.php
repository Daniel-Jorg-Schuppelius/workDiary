<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotSplitTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockLotStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, StockMovement, StockValuationLayer, Warehouse, WarehouseBin};
use App\Models\Platform\User;
use App\Services\Inventory\{FefoValuationService, InventoryLedger, LotService, LotSplitService, LotStockReader, StockPosting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Los-Split/-Merge (Feature 047/048, E6/E7): Bestand zwischen Chargen über
 * Gegenbuchungen verschieben, Einzelkosten erhalten, Mengen konsistent.
 */
final class LotSplitTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LotService $lots;
    private LotSplitService $split;
    private ArticleVariant $variant;
    private Warehouse $warehouse;
    private ?User $actor = null;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->lots = app(LotService::class);
        $this->split = app(LotSplitService::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'batch_required' => true]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_split_moves_quantity_keeping_cost(): void {
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $source);

        $target = $this->split->split($source, '4', 'L1-A', actorUserId: $this->actor()->id);

        $this->assertSame('6.0000', $this->layerQty($source));
        $this->assertSame('4.0000', $this->layerQty($target));
        $this->assertSame('2.0000', StockValuationLayer::query()->where('stock_lot_id', $target->id)->value('unit_cost')?->getAmount());

        // Im Lagerbuch eine Umbuchung: Abgang an der Quelle, Zugang an der abgeteilten Charge, ohne Kosten.
        $this->assertSame(['6.0000', '4.0000'], [$this->stock()->balanceOf($source), $this->stock()->balanceOf($target)]);
        $this->assertSame('10.0000', app(InventoryLedger::class)->balance($this->variant, $this->warehouse, StockState::Physical));
        $moves = StockMovement::query()->whereIn('movement_type', [StockMovementType::TransferOut->value, StockMovementType::TransferIn->value])->orderBy('id')->get();
        $prefix = 'lot-split:' . $source->id . ':' . $target->id . ':1:';
        $this->assertSame(
            [[StockMovementType::TransferOut, $source->id, '-4.0000', $prefix . 'out'], [StockMovementType::TransferIn, $target->id, '4.0000', $prefix . 'in']],
            $moves->map(fn (StockMovement $m): array => [$m->movement_type, $m->stock_lot_id, $m->qty_base, $m->idempotency_key])->all(),
        );
        foreach ($moves as $movement) {
            $this->assertSame([null, $source->getMorphClass(), $source->id, $this->actor()->id], [$movement->cost_total, $movement->source_type, $movement->source_id, $movement->actor_user_id]);
        }
    }

    /**
     * Teilen nimmt die Menge Topf für Topf (Lager, Platz, Eigentum aufsteigend);
     * die Schichten folgen den gebuchten Lagern, der Rest in Erwerbsfolge.
     */
    public function test_split_books_pot_by_pot_and_the_layers_follow_the_warehouses(): void {
        $ledger = app(InventoryLedger::class);
        $first = $this->warehouse;
        $second = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $first->id, 'code' => 'R-01']);
        $source = $this->lots->register($this->variant, 'L1');
        // Die Schicht im zweiten Lager ist die ältere.
        $this->lots->receiveIntoLot($this->variant, $second, '5', '3', $source);
        $this->lots->receiveIntoLot($this->variant, $first, '4', '2', $source);
        $ledger->post(new StockPosting($this->variant, $first, StockState::Physical, '3.0000', StockMovementType::Receipt, stockLotId: $source->id, bin: $bin));

        $target = $this->split->split($source, '6', 'L1-A');

        $this->assertSame(
            [[$first->id, null, '4.0000', 1], [$first->id, $bin->id, '2.0000', 2]],
            StockMovement::query()->where('stock_lot_id', $target->id)->orderBy('id')->get()
                ->map(fn (StockMovement $m): array => [$m->warehouse_id, $m->bin_id, $m->qty_base, (int) explode(':', (string) $m->idempotency_key)[3]])->all(),
        );
        $this->assertSame(['6.0000', '0.0000'], [$this->stock()->balanceOf($target, $first), $this->stock()->balanceOf($target, $second)]);
        $this->assertSame(['1.0000', '5.0000'], [$this->stock()->balanceOf($source, $first), $this->stock()->balanceOf($source, $second)]);
        // Erst die Schicht des gebuchten Lagers, dann der Rest aus der ältesten Schicht.
        $this->assertSame(
            [[$first->id, '4.0000', '2.0000'], [$second->id, '2.0000', '3.0000']],
            StockValuationLayer::query()->where('stock_lot_id', $target->id)->orderBy('id')->get()
                ->map(fn (StockValuationLayer $l): array => [$l->warehouse_id, $l->qty_remaining, $l->unit_cost?->getAmount()])->all(),
        );
        $this->assertSame('9.0000', bcadd($this->layerQty($source), $this->layerQty($target), 4));
    }

    /** Die Umbuchung läuft durch die Platzsperre des Lagerbuchs — scheitert sie, entsteht keine Charge. */
    public function test_split_fails_as_a_whole_when_the_ledger_rejects_a_posting(): void {
        $ledger = app(InventoryLedger::class);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R-07']);
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '2', '2', $source);
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '4.0000', StockMovementType::Receipt, stockLotId: $source->id, bin: $bin));
        $bin->update(['blocked' => true]);

        try {
            $this->split->split($source, '5', 'L1-A');
            $this->fail('Ein gesperrter Lagerplatz nimmt auch keine Umbuchung an.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.error.bin_unusable', ['code' => 'R-07']), $e->getMessage());
        }

        $this->assertSame(2, StockMovement::query()->count());
        $this->assertNull(StockLot::query()->where('lot_no', 'L1-A')->first());
        $this->assertSame(['6.0000', '2.0000'], [$this->stock()->balanceOf($source), $this->layerQty($source)]);
    }

    public function test_split_beyond_stock_throws(): void {
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $source);

        $this->expectException(RuntimeException::class);
        $this->split->split($source, '5', 'L1-A');
    }

    public function test_merge_combines_lots(): void {
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '6', '2', $a);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);

        $this->split->merge($b, $a);

        $this->assertSame('11.0000', $this->stock()->balanceOf($a));
        $this->assertSame('0.0000', $this->stock()->balanceOf($b));
        $this->assertSame(StockLotStatus::Merged, $b->fresh()->status);
    }

    /** Der Bestand wandert über Gegenbuchungen; Summe, Verfügbarkeit und Bewertung bleiben gleich. */
    public function test_merge_moves_the_ledger_stock_by_counter_postings(): void {
        $ledger = app(InventoryLedger::class);
        $fefo = app(FefoValuationService::class);
        $a = $this->lots->register($this->variant, 'A', '2026-06-01');
        $b = $this->lots->register($this->variant, 'B', '2026-09-01');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '6', '2', $a);
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);

        $before = [
            $ledger->balance($this->variant, $this->warehouse, StockState::Physical),
            $ledger->available($this->variant, $this->warehouse),
            $fefo->onHand($this->variant, $this->warehouse),
            $fefo->totalValue($this->variant, $this->warehouse),
        ];
        $journal = StockMovement::query()->orderBy('id')->get()->map->getAttributes()->all();
        $this->assertSame(['11.0000', '11.0000', '11.0000', '27.0000'], $before);

        $this->split->merge($b, $a, $this->actor()->id);

        $this->assertSame($before, [
            $ledger->balance($this->variant, $this->warehouse, StockState::Physical),
            $ledger->available($this->variant, $this->warehouse),
            $fefo->onHand($this->variant, $this->warehouse),
            $fefo->totalValue($this->variant, $this->warehouse),
        ]);
        $this->assertSame('0.0000', $this->ledgerBalance($b));
        $this->assertSame('11.0000', $this->ledgerBalance($a));

        // Das Journal wächst nur: die alten Zeilen stehen unverändert da.
        $after = StockMovement::query()->orderBy('id')->get();
        $this->assertSame($journal, $after->take(2)->map->getAttributes()->all());
        $this->assertCount(4, $after);
        [$out, $in] = [$after[2], $after[3]];
        $this->assertSame([StockMovementType::TransferOut, '-5.0000', $b->id], [$out->movement_type, $out->qty_base, $out->stock_lot_id]);
        $this->assertSame([StockMovementType::TransferIn, '5.0000', $a->id], [$in->movement_type, $in->qty_base, $in->stock_lot_id]);
        foreach ([$out, $in] as $movement) {
            $this->assertNull($movement->cost_total);
            $this->assertSame($b->getMorphClass(), $movement->source_type);
            $this->assertSame($b->id, $movement->source_id);
            $this->assertSame($this->actor()->id, $movement->actor_user_id);
        }

        $this->assertSame($a->id, $b->fresh()?->merged_into_lot_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'stock_lot.merged', 'auditable_id' => $b->id]);
    }

    /** Je Lagerplatz und Bestandszustand wandert genau der Saldo dieses Topfes. */
    public function test_merge_keeps_bin_and_state_of_the_stock(): void {
        $ledger = app(InventoryLedger::class);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R-01']);
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '4.0000', StockMovementType::Receipt, stockLotId: $b->id, bin: $bin));
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '2.0000', StockMovementType::Receipt, stockLotId: $b->id));
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Quality, '1.0000', StockMovementType::Return, stockLotId: $b->id));

        $this->split->merge($b, $a);

        $moved = StockMovement::query()->where('stock_lot_id', $a->id)->orderBy('id')->get()
            ->map(fn (StockMovement $m): array => [$m->bin_id, $m->stock_state, $m->qty_base])->all();
        $this->assertEqualsCanonicalizing([[$bin->id, StockState::Physical, '4.0000'], [null, StockState::Physical, '2.0000'], [null, StockState::Quality, '1.0000']], $moved);
        $this->assertSame('4.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical, bin: $bin));
        $this->assertSame('6.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Physical));
        $this->assertSame('1.0000', $ledger->balance($this->variant, $this->warehouse, StockState::Quality));
        $this->assertSame('0.0000', $this->ledgerBalance($b, StockState::Quality));
    }

    public function test_merge_needs_two_active_lots(): void {
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $c = $this->lots->register($this->variant, 'C');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);
        $this->lots->block($b, 'Reklamation', $this->actor());

        // Gesperrter Bestand kommt nicht über eine Zusammenführung in den Umlauf — in keiner Richtung.
        foreach ([[$b, $a], [$a, $b]] as [$from, $into]) {
            try {
                $this->split->merge($from, $into);
                $this->fail('Zusammenführen mit einer gesperrten Charge muss scheitern.');
            } catch (RuntimeException) {
            }
        }
        // Zugang und Sperrbuchung, keine Umbuchung.
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame('5.0000', $this->stock()->balanceOf($b));

        // Zusammengeführt ist Endzustand: weder Quelle noch Ziel.
        $this->split->merge($c, $a);
        foreach ([[$c, $a], [$a, $c]] as [$from, $into]) {
            try {
                $this->split->merge($from, $into);
                $this->fail('Eine zusammengeführte Charge nimmt an keiner Zusammenführung mehr teil.');
            } catch (RuntimeException) {
            }
        }
        $this->assertSame(StockLotStatus::Active, $a->fresh()?->status);
    }

    /** Die Umbuchung läuft durch das Lagerbuch und damit durch dessen Platzsperre — ganz oder gar nicht. */
    public function test_merge_fails_as_a_whole_when_the_ledger_rejects_a_posting(): void {
        $ledger = app(InventoryLedger::class);
        $bin = WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => 'R-09']);
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '5', '3', $b);
        $ledger->post(new StockPosting($this->variant, $this->warehouse, StockState::Physical, '4.0000', StockMovementType::Receipt, stockLotId: $b->id, bin: $bin));
        $bin->update(['blocked' => true]);

        try {
            $this->split->merge($b, $a);
            $this->fail('Ein gesperrter Lagerplatz nimmt auch keine Umbuchung an.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.error.bin_unusable', ['code' => 'R-09']), $e->getMessage());
        }

        // Auch die Umbuchung ohne Platz und die Schichten sind zurückgerollt.
        $this->assertSame(2, StockMovement::query()->count());
        $this->assertSame(StockLotStatus::Active, $b->fresh()?->status);
        $this->assertNull($b->fresh()?->merged_into_lot_id);
        $this->assertSame('9.0000', $this->stock()->balanceOf($b));
        $this->assertSame('0.0000', $this->stock()->balanceOf($a));
    }

    public function test_blocked_lot_cannot_be_split(): void {
        $source = $this->lots->register($this->variant, 'L1');
        $this->lots->receiveIntoLot($this->variant, $this->warehouse, '10', '2', $source);
        $this->lots->block($source, 'Reklamation', $this->actor());

        try {
            $this->split->split($source, '4', 'L1-A');
            $this->fail('Aus einer gesperrten Charge darf keine aktive entstehen.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.lot.error.not_active', ['lot' => 'L1']), $e->getMessage());
        }
        $this->assertSame(1, StockLot::query()->count());
    }

    public function test_merged_lot_takes_no_further_receipt(): void {
        $a = $this->lots->register($this->variant, 'A');
        $b = $this->lots->register($this->variant, 'B');
        $this->split->merge($b, $a);

        try {
            $this->lots->receiveIntoLot($this->variant, $this->warehouse, '3', '2', $this->lots->register($this->variant, 'B'));
            $this->fail('Zugang an eine zusammengeführte Charge muss scheitern.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.lot.error.receipt_into_merged', ['lot' => 'B']), $e->getMessage());
        }
        $this->assertSame(0, StockMovement::query()->count());
    }

    private function stock(): LotStockReader {
        return app(LotStockReader::class);
    }

    private function layerQty(StockLot $lot): string {
        return bcadd((string) StockValuationLayer::query()->where('stock_lot_id', $lot->id)->sum('qty_remaining'), '0', 4);
    }

    private function ledgerBalance(StockLot $lot, StockState $state = StockState::Physical): string {
        return bcadd((string) StockMovement::query()->where('stock_lot_id', $lot->id)->where('stock_state', $state->value)->sum('qty_base'), '0', 4);
    }

    private function actor(): User {
        return $this->actor ??= User::factory()->create(['organization_id' => $this->organization->id]);
    }
}
