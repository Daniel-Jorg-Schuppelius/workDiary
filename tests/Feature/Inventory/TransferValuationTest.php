<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TransferValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Inventory\{ScanAction, StockMovementType};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{FifoValuationService, InventoryLedger, InventoryValuationManager, LotService, ScanActionService, ValuationService};
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1726: Die Umlagerung nimmt das Bewertungsbuch mit — Abgang im Quelllager
 * nach dessen Verfahren, Zugang im Ziellager zum selben Wert. Der Bestandswert
 * der Organisation bleibt gleich; FIFO trägt die verbrauchten Schichten mit
 * Stückwert und Zugangsdatum ins Ziellager.
 */
final class TransferValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $main;

    private Warehouse $van;

    private Article $article;

    private ArticleVariant $variant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->main = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->van = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'MUFFE-22',
            'currency' => 'EUR',
        ]);
    }

    private function useFifo(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
    }

    /** @return numeric-string Bestandswert der Variante über alle Lager nach dem wirksamen Verfahren */
    private function stockValue(): string {
        $sum = '0';
        foreach ([$this->main, $this->van] as $warehouse) {
            $summary = app(InventoryValuationManager::class)->summariesForWarehouse($warehouse, $this->organization)[$this->variant->id] ?? null;
            $sum = bcadd($sum, $summary['value'] ?? '0', 4);
        }

        return $sum;
    }

    private function book(Warehouse $warehouse): StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $warehouse->id)->sole();
    }

    private function scanTransfer(string $qty, ?string $code = 'MUFFE-22'): void {
        app(ScanActionService::class)->book((string) $code, ScanAction::Transfer, $this->main, $qty, ['target' => $this->van]);
    }

    public function test_moving_average_moves_the_value_and_the_target_average_follows(): void {
        app(ValuationService::class)->receipt($this->variant, $this->main, '10', '4.00');
        app(ValuationService::class)->receipt($this->variant, $this->van, '2', '7.00');
        $this->assertSame('54.0000', $this->stockValue());

        $this->scanTransfer('4');

        $out = StockMovement::query()->where('movement_type', StockMovementType::TransferOut->value)->sole();
        $in = StockMovement::query()->where('movement_type', StockMovementType::TransferIn->value)->sole();
        $this->assertSame(['-4.0000', '4.0000', '16.0000', 'EUR'], [(string) $out->qty_base, $out->cost_unit?->getAmount(), $out->cost_total?->getAmount(), $out->currency?->value]);
        $this->assertSame(['4.0000', '4.0000', '16.0000', (int) $this->van->id], [(string) $in->qty_base, $in->cost_unit?->getAmount(), $in->cost_total?->getAmount(), (int) $in->warehouse_id]);
        $this->assertSame(InventoryLedger::TRANSFER_IN_KEY_PREFIX . $out->id, $in->idempotency_key);

        // Quelle: Menge sinkt, Durchschnitt bleibt; Ziel: (2 × 7 + 4 × 4) / 6 = 5.
        $this->assertSame(['6.0000', '4.0000'], [bcadd((string) $this->book($this->main)->qty_on_hand, '0', 4), $this->book($this->main)->avg_cost?->getAmount()]);
        $this->assertSame(['6.0000', '5.0000'], [bcadd((string) $this->book($this->van)->qty_on_hand, '0', 4), $this->book($this->van)->avg_cost?->getAmount()]);
        $this->assertSame('54.0000', $this->stockValue(), 'Die Umlagerung ändert den Bestandswert nicht.');

        // Gegenprobe: ein Abgang im Ziellager geht zum neuen Durchschnitt ab, nicht zu dem der Quelle.
        $issue = app(ValuationService::class)->issue($this->variant, $this->van, '1');
        $this->assertSame('5.0000', $issue->first()->cost_unit?->getAmount());
    }

    public function test_fifo_carries_the_consumed_layers_with_their_cost_and_age(): void {
        $this->useFifo();
        Carbon::setTestNow('2026-03-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->main, '3', '2.00');
        Carbon::setTestNow('2026-04-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->main, '5', '3.00');
        Carbon::setTestNow('2026-05-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->van, '1', '9.00');
        $this->assertSame('30.0000', $this->stockValue());

        Carbon::setTestNow('2026-06-01 08:00:00');
        $this->scanTransfer('4');

        $out = StockMovement::query()->where('movement_type', StockMovementType::TransferOut->value)->sole();
        $in = StockMovement::query()->where('movement_type', StockMovementType::TransferIn->value)->sole();
        // 3 × 2,00 + 1 × 3,00 = 9,00 — der Zugang trägt denselben Wert.
        $this->assertSame(['9.0000', '2.2500'], [$out->cost_total?->getAmount(), $out->cost_unit?->getAmount()]);
        $this->assertSame('9.0000', $in->cost_total?->getAmount());

        $arrived = StockValuationLayer::query()->where('warehouse_id', $this->van->id)->where('source_movement_id', $in->id)->orderBy('acquired_at')->get();
        $this->assertSame(
            [['3.0000', '2.0000', '2026-03-01'], ['1.0000', '3.0000', '2026-04-01']],
            $arrived->map(fn (StockValuationLayer $layer): array => [bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount(), $layer->acquired_at->toDateString()])->all(),
        );
        $this->assertSame('4.0000', bcadd((string) StockValuationLayer::query()->where('warehouse_id', $this->main->id)->sum('qty_remaining'), '0', 4));
        $this->assertSame('30.0000', $this->stockValue(), 'Die Umlagerung ändert den Bestandswert nicht.');
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists(), 'Das Durchschnittsbuch bleibt bei FIFO still.');

        // Gegenprobe: die umgelagerten Schichten sind älter als die vorhandene — ein Abgang im Ziel nimmt sie zuerst.
        $issue = app(FifoValuationService::class)->issue($this->variant, $this->van, '4');
        $this->assertSame('9.0000', $issue->costTotal());
        Carbon::setTestNow();
    }

    public function test_lot_layers_keep_lot_and_best_before_in_the_target(): void {
        $this->useFifo();
        $lots = app(LotService::class);
        $late = $lots->register($this->variant, 'CH-SPAET', '2099-09-30');
        $early = $lots->register($this->variant, 'CH-FRUEH', '2099-03-31');
        $lots->receiveIntoLot($this->variant, $this->main, '2', '5.00', $late);
        $lots->receiveIntoLot($this->variant, $this->main, '2', '4.00', $early);

        $this->scanTransfer('3');

        $arrived = StockValuationLayer::query()->where('warehouse_id', $this->van->id)->orderBy('id')->get();
        $this->assertSame(
            [[$early->id, '2.0000', '4.0000', '2099-03-31'], [$late->id, '1.0000', '5.0000', '2099-09-30']],
            $arrived->map(fn (StockValuationLayer $layer): array => [(int) $layer->stock_lot_id, bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount(), $layer->best_before?->toDateString()])->all(),
        );
        $this->assertSame('18.0000', $this->stockValue());
    }

    public function test_a_repeated_transfer_with_its_key_moves_the_book_once(): void {
        app(ValuationService::class)->receipt($this->variant, $this->main, '10', '4.00');
        $strategy = app(InventoryValuationManager::class)->forVariant($this->variant, $this->organization);

        $first = $strategy->transfer($this->variant, $this->main, $this->van, '3', idempotencyKey: 'umlagerung-1');
        $again = $strategy->transfer($this->variant, $this->main, $this->van, '3', idempotencyKey: 'umlagerung-1');

        $this->assertSame($first->first()->id, $again->first()->id);
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::TransferIn->value)->count());
        $this->assertSame('3.0000', bcadd((string) $this->book($this->van)->qty_on_hand, '0', 4));
        $this->assertSame('7.0000', bcadd((string) $this->book($this->main)->qty_on_hand, '0', 4));

        // Gegenprobe: ein anderer Schlüssel ist eine neue Umlagerung.
        $strategy->transfer($this->variant, $this->main, $this->van, '1', idempotencyKey: 'umlagerung-2');
        $this->assertSame('4.0000', bcadd((string) $this->book($this->van)->qty_on_hand, '0', 4));
    }

    public function test_a_fifo_repeat_adds_no_second_layer(): void {
        $this->useFifo();
        app(FifoValuationService::class)->receipt($this->variant, $this->main, '5', '2.00');
        $strategy = app(InventoryValuationManager::class)->forVariant($this->variant, $this->organization);

        $strategy->transfer($this->variant, $this->main, $this->van, '2', idempotencyKey: 'umlagerung-fifo');
        $strategy->transfer($this->variant, $this->main, $this->van, '2', idempotencyKey: 'umlagerung-fifo');

        $this->assertSame(1, StockValuationLayer::query()->where('warehouse_id', $this->van->id)->count());
        $this->assertSame('3.0000', bcadd((string) StockValuationLayer::query()->where('warehouse_id', $this->main->id)->sum('qty_remaining'), '0', 4));
        $this->assertSame('10.0000', $this->stockValue());
    }

    public function test_a_transfer_beyond_the_available_stock_books_nothing(): void {
        app(ValuationService::class)->receipt($this->variant, $this->main, '2', '4.00');

        try {
            $this->scanTransfer('3');
            $this->fail('Die Umlagerung über den Bestand muss scheitern.');
        } catch (RuntimeException) {
            // erwartet
        }

        $this->assertSame(0, StockMovement::query()->whereIn('movement_type', [StockMovementType::TransferOut->value, StockMovementType::TransferIn->value])->count());
        $this->assertSame('2.0000', bcadd((string) $this->book($this->main)->qty_on_hand, '0', 4));
        $this->assertFalse(StockValuation::query()->where('warehouse_id', $this->van->id)->exists());
    }

    public function test_the_scan_form_books_a_valued_transfer(): void {
        app(ValuationService::class)->receipt($this->variant, $this->main, '5', '3.00');
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($teamlead)->post(route('inventory.scan.book'), [
            'code' => 'MUFFE-22',
            'action' => ScanAction::Transfer->value,
            'warehouse' => $this->main->sqid,
            'target' => $this->van->sqid,
            'qty' => '2',
        ])->assertSessionHas('success', __('inventory.scan.booked'));

        $this->assertSame(['2.0000', '3.0000'], [bcadd((string) $this->book($this->van)->qty_on_hand, '0', 4), $this->book($this->van)->avg_cost?->getAmount()]);
        $this->assertSame('15.0000', $this->stockValue());
    }
}
