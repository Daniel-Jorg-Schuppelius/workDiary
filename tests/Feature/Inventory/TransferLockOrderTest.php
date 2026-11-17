<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferLockOrderTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\{FifoValuationService, InventoryValuationManager, ValuationService};
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1738: Eine Umlagerung sperrt vor der ersten Buchung Bestand und
 * Bewertungsbuch beider Lager, je in aufsteigender Lager-ID — nicht Quelle vor
 * Ziel. Zwei gegenläufige Umlagerungen (A→B, B→A) warten so aufeinander, statt
 * sich zu verklemmen. Belegt über die Abfragefolge; die Sperrklausel selbst
 * schreibt nur MariaDB, die Folge ist auf jedem Treiber dieselbe.
 */
final class TransferLockOrderTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $low;

    private Warehouse $high;

    private Article $article;

    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->low = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->high = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertLessThan($this->high->id, $this->low->id);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'KUPPLUNG-12',
            'currency' => 'EUR',
        ]);
    }

    public function test_moving_average_locks_both_books_in_warehouse_order_before_booking(): void {
        app(ValuationService::class)->receipt($this->variant, $this->low, '3', '6.00');
        app(ValuationService::class)->receipt($this->variant, $this->high, '5', '4.00');
        $before = $this->stockValue();

        // Von der höheren zur niedrigeren ID: vorher sperrte die Quelle (hoch) zuerst.
        $order = $this->lockOrder(fn () => $this->transfer($this->high, $this->low, '2'), 'stock_valuations');

        $this->assertSame([
            ['stock_movements', $this->low->id],
            ['stock_movements', $this->high->id],
            ['stock_valuations', $this->low->id],
            ['stock_valuations', $this->high->id],
        ], $order);
        $this->assertSame($before, $this->stockValue(), 'Die Umlagerung bleibt wertneutral.');

        // Gegenrichtung: dieselbe Folge.
        $this->assertSame($order, $this->lockOrder(fn () => $this->transfer($this->low, $this->high, '1'), 'stock_valuations'));
    }

    public function test_fifo_locks_the_layers_of_both_warehouses_in_warehouse_order_before_booking(): void {
        $this->article->update(['valuation_method' => 'fifo']);
        app(FifoValuationService::class)->receipt($this->variant, $this->low, '2', '6.00');
        app(FifoValuationService::class)->receipt($this->variant, $this->high, '4', '3.00');
        $before = $this->stockValue();

        $order = $this->lockOrder(fn () => $this->transfer($this->high, $this->low, '3'), 'stock_valuation_layers');

        $this->assertSame([
            ['stock_movements', $this->low->id],
            ['stock_movements', $this->high->id],
            ['stock_valuation_layers', $this->low->id],
            ['stock_valuation_layers', $this->high->id],
        ], $order);
        $this->assertSame($before, $this->stockValue());
    }

    private function transfer(Warehouse $from, Warehouse $to, string $qty): void {
        app(InventoryValuationManager::class)->forVariant($this->variant->fresh() ?? $this->variant, $this->organization)->transfer($this->variant, $from, $to, $qty);
    }

    /**
     * Lesezugriffe auf Bestand (`stock_movements`) und Buch je Lager vor der ersten Buchung, in der Folge ihres ersten
     * Auftretens.
     *
     * @param  callable(): void  $transfer
     * @return list<array{0: string, 1: int}>
     */
    private function lockOrder(callable $transfer, string $book): array {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = [$query->sql, $query->bindings];
        });
        $transfer();

        $order = [];
        foreach ($queries as [$sql, $bindings]) {
            if (preg_match('/^insert into [`"]?stock_movements[`"]?/i', $sql) === 1) {
                break;
            }
            if (preg_match('/^select .*? from [`"]?(stock_movements|' . $book . ')[`"]?\s/is', $sql, $table) !== 1) {
                continue;
            }
            $warehouseId = self::bound($sql, $bindings, 'warehouse_id');
            $entry = [$table[1], (int) $warehouseId];
            if ($warehouseId !== null && ! in_array($entry, $order, true)) {
                $order[] = $entry;
            }
        }

        return $order;
    }

    /**
     * Wert, der an `$column = ?` gebunden ist.
     *
     * @param  array<int|string, mixed>  $bindings
     */
    private static function bound(string $sql, array $bindings, string $column): mixed {
        $parts = explode('?', $sql);
        foreach (array_values($bindings) as $index => $value) {
            if (preg_match('/[`"]?' . $column . '[`"]?\s*=\s*$/', $parts[$index] ?? '') === 1) {
                return $value;
            }
        }

        return null;
    }

    /** @return numeric-string Bestandswert beider Lager nach dem wirksamen Verfahren */
    private function stockValue(): string {
        $sum = '0';
        foreach ([$this->low, $this->high] as $warehouse) {
            $summary = app(InventoryValuationManager::class)->summariesForWarehouse($warehouse, $this->organization)[$this->variant->id] ?? null;
            $sum = bcadd($sum, $summary['value'] ?? '0', 4);
        }

        return $sum;
    }
}
