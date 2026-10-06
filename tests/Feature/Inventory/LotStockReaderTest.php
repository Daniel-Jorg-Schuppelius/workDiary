<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LotStockReaderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{StockLotStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockLot, Warehouse, WarehouseBin};
use App\Services\Inventory\{InventoryLedger, LotStockReader, StockPosting};
use CommonToolkit\ValueObjects\Decimal;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Chargenbestand aus dem Lagerbuch (Feature 048, E2): Saldo je Platz, nur
 * physischer Bestand, Zusammenführungen bei der Zielcharge, gesperrte Chargen
 * getrennt, FEFO-Folge und gesperrtes Lesen für die Zuteilung.
 */
final class LotStockReaderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private LotStockReader $reader;
    private ArticleVariant $variant;
    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->reader = app(LotStockReader::class);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $article = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'option_signature' => 'default']);
    }

    public function test_balances_per_bin_count_only_physical_lot_stock(): void {
        $r1 = $this->bin('R1');
        $r2 = $this->bin('R2');
        $a = $this->lot('L-A');
        $b = $this->lot('L-B');
        $this->book($a, '3', $r1);
        $this->book($a, '2');
        $this->book($a, '-1', $r1, StockMovementType::Issue);
        $this->book($b, '4', $r2);
        $this->book($b, '5', $r2, StockMovementType::Return, StockState::Quality);
        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '7', bin: $r1);

        $this->assertSame(
            [0 => ['L-A' => '2.0000'], $r1->id => ['L-A' => '2.0000'], $r2->id => ['L-B' => '4.0000']],
            $this->flatten($this->reader->balances($this->variant, $this->warehouse)),
        );
        $this->assertSame([$r1->id => ['L-A' => '2.0000']], $this->flatten($this->reader->balances($this->variant, $this->warehouse, $r1)));
        $this->assertSame(['4.0000', '4.0000'], [$this->reader->balanceOf($a), $this->reader->balanceOf($b)]);
        $this->assertSame('0.0000', $this->reader->balanceOf($a, Warehouse::factory()->create(['organization_id' => $this->organization->id])));
    }

    /** Altchargen einer Kette zählen bei der heutigen Charge; ohne bekannte Zielcharge bei keiner. */
    public function test_merge_chain_counts_at_the_current_lot(): void {
        $a = $this->lot('L-A');
        $b = $this->lot('L-B');
        $c = $this->lot('L-C');
        $lost = $this->lot('L-LOST');
        foreach ([[$a, '1'], [$b, '2'], [$c, '4'], [$lost, '8']] as [$lot, $qty]) {
            $this->book($lot, $qty);
        }
        DB::table('stock_lots')->where('id', $c->id)->update(['status' => StockLotStatus::Merged->value, 'merged_into_lot_id' => $b->id]);
        DB::table('stock_lots')->where('id', $b->id)->update(['status' => StockLotStatus::Merged->value, 'merged_into_lot_id' => $a->id]);
        DB::table('stock_lots')->where('id', $lost->id)->update(['status' => StockLotStatus::Merged->value]);
        $lots = StockLot::query()->orderBy('id')->get();

        $this->assertSame([0 => ['L-A' => '7.0000']], $this->flatten($this->reader->balances($this->variant, $this->warehouse)));
        $this->assertSame(
            [$a->id => '7.0000', $b->id => '0.0000', $c->id => '0.0000', $lost->id => '0.0000'],
            $this->reader->balancesOf($lots),
        );
    }

    /** Entnehmbar in FEFO-Folge; gesperrte Chargen nur als Summe, Chargen ohne Bestand gar nicht. */
    public function test_pickable_orders_fefo_and_sums_blocked_lots(): void {
        foreach ([['L-NONE', null, '1'], ['L-LATE', '2027-01-01', '1'], ['L-B', '2026-06-01', '1'], ['L-A', '2026-06-01', '1'], ['L-HELD', '2026-01-01', '3'], ['L-EMPTY', '2026-02-01', '0']] as [$no, $bestBefore, $qty]) {
            $this->book($this->lot($no, $bestBefore), $qty);
        }
        DB::table('stock_lots')->where('lot_no', 'L-HELD')->update(['status' => StockLotStatus::Blocked->value]);

        [$pickable, $blocked] = $this->reader->pickable($this->reader->balances($this->variant, $this->warehouse), 0);

        $this->assertSame(['L-A', 'L-B', 'L-LATE', 'L-NONE'], array_map(fn (array $entry): string => $entry[0]->lot_no, $pickable));
        $this->assertSame('3.0000', $blocked->getValue());
    }

    /** Für die Zuteilung liest der Dienst gesperrt — in der Transaktion des Abgangs. */
    public function test_balances_for_update_lock_the_rows_inside_the_transaction(): void {
        $lot = $this->lot('L-A');
        $this->book($lot, '3');
        $locking = [];
        DB::listen(function (QueryExecuted $query) use (&$locking): void {
            if (str_contains($query->sql, 'stock_movements')) {
                $locking[] = str_contains(strtolower($query->sql), 'for update');
            }
        });

        $plain = $this->reader->balances($this->variant, $this->warehouse);
        $locked = DB::transaction(fn (): array => $this->reader->balances($this->variant, $this->warehouse, forUpdate: true));

        $this->assertSame($this->flatten($plain), $this->flatten($locked));
        $supportsLock = in_array(DB::getDriverName(), ['mysql', 'mariadb', 'pgsql'], true);
        $this->assertSame([false, $supportsLock], $locking);
    }

    /** Ohne Platz teilt die Zuteilung über den Chargenbestand des ganzen Lagers zu. */
    public function test_allocate_without_bin_uses_lot_stock_across_places(): void {
        $early = $this->lot('L-EARLY', '2026-05-01');
        $late = $this->lot('L-LATE', '2026-09-01');
        $this->book($early, '2', $this->bin('R1'));
        $this->book($late, '2', $this->bin('R2'));

        $parts = DB::transaction(fn (): array => $this->reader->allocate($this->variant, $this->warehouse, '5')->parts);

        $this->assertSame(
            [['L-EARLY', '2.0000'], ['L-LATE', '2.0000'], [null, '1.0000']],
            array_map(fn (array $part): array => [$part['lot']?->lot_no, $part['qty']], $parts),
        );
    }

    private function lot(string $lotNo, ?string $bestBefore = null): StockLot {
        return StockLot::factory()->create(['organization_id' => $this->organization->id, 'article_variant_id' => $this->variant->id, 'lot_no' => $lotNo, 'best_before' => $bestBefore]);
    }

    private function bin(string $code): WarehouseBin {
        return WarehouseBin::factory()->create(['organization_id' => $this->organization->id, 'warehouse_id' => $this->warehouse->id, 'code' => $code]);
    }

    private function book(StockLot $lot, string $qty, ?WarehouseBin $bin = null, StockMovementType $type = StockMovementType::Receipt, StockState $state = StockState::Physical): void {
        app(InventoryLedger::class)->post(new StockPosting($this->variant, $this->warehouse, $state, $qty, $type, stockLotId: $lot->id, bin: $bin));
    }

    /**
     * @param  array<int, array<int, array{0: StockLot, 1: Decimal}>>  $balances
     * @return array<int, array<string, string>>
     */
    private function flatten(array $balances): array {
        ksort($balances);

        return array_map(function (array $lots): array {
            $out = [];
            foreach ($lots as [$lot, $balance]) {
                $out[$lot->lot_no] = $balance->getValue();
            }
            ksort($out);

            return $out;
        }, $balances);
    }
}
