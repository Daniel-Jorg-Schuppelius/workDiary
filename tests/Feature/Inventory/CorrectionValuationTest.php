<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CorrectionValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\Integration\ExternalConflictStatus;
use App\Enums\Inventory\{OwnershipType, StockCountStatus, StockMovementType, StockState};
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Audit\AuditLog;
use App\Models\Integration\PendingExternalConflict;
use App\Models\Inventory\{StockCount, StockCountLine, StockMovement, StockValuation, StockValuationLayer, Warehouse};
use App\Models\Platform\User;
use App\Services\Inventory\{FifoValuationService, InventoryConflictResolver, InventoryLedger, StockCorrection, StockPosting, StocktakeService, ValuationService};
use App\Support\MorphMap;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1727: Inventurdifferenz, Gegenbuchung eines Spiegelkonflikts und
 * Korrekturen bewegen das Bewertungsbuch — Mehrbestand wie ein manueller
 * Eingang (Einkaufspreis, sonst Durchschnitt), Minderbestand nach Verfahren.
 * Andere Zustände und Fremdbestand bleiben wertneutral; jede Korrektur bucht
 * und bewertet genau einmal. Die Vier-Augen-Freigabe misst den gebuchten Wert.
 */
final class CorrectionValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $warehouse;

    private Article $article;

    private ArticleVariant $variant;

    private StocktakeService $stocktake;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'base_unit' => 'Stk', 'currency' => 'EUR']);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->article->id,
            'is_default' => true,
            'option_signature' => 'default',
            'sku' => 'DICHTUNG-8',
            'currency' => 'EUR',
        ]);
        $this->stocktake = app(StocktakeService::class);
    }

    /** FIFO als Artikelverfahren: die Einstellungen der Organisation bleiben für die Vier-Augen-Regel frei. */
    private function useFifo(): void {
        $this->article->update(['valuation_method' => 'fifo']);
    }

    private function book(): StockValuation {
        return StockValuation::query()->where('article_variant_id', $this->variant->id)->where('warehouse_id', $this->warehouse->id)->sole();
    }

    private function line(StockCount $count, StockState $state = StockState::Physical, OwnershipType $ownership = OwnershipType::Own): StockCountLine {
        return $count->lines()->where('stock_state', $state->value)->where('ownership_type', $ownership->value)->sole();
    }

    private function countPhysical(string $counted, ?int $countedBy = null): StockCount {
        $count = $this->stocktake->open($this->warehouse);
        $this->stocktake->recordCount($this->line($count), $counted, $countedBy);

        return $count;
    }

    private function lastCorrection(): StockMovement {
        return StockMovement::query()->where('movement_type', StockMovementType::Correction->value)->latest('id')->firstOrFail();
    }

    /** @return numeric-string */
    private function layerValue(): string {
        $sum = '0';
        foreach (StockValuationLayer::query()->where('article_variant_id', $this->variant->id)->where('qty_remaining', '>', 0)->get() as $layer) {
            $sum = bcadd($sum, bcmul((string) $layer->qty_remaining, $layer->unit_cost?->getAmount() ?? '0', 4), 4);
        }

        return $sum;
    }

    private function conflictFor(StockMovement $movement): PendingExternalConflict {
        return PendingExternalConflict::query()->create([
            'organization_id' => $this->organization->id,
            'plugin_id' => 'jtl',
            'conflict_type' => 'inventory_outbox',
            'referenceable_type' => $movement->getMorphClass(),
            'referenceable_id' => $movement->id,
            'local_snapshot' => ['qty_base' => $movement->qty_base, 'stock_state' => $movement->stock_state->value],
            'remote_snapshot' => [],
            'status' => ExternalConflictStatus::Open,
        ]);
    }

    public function test_a_shortage_leaves_at_the_moving_average(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '5.00');
        $count = $this->countPhysical('8');

        $this->stocktake->applyDifferences($count);

        $correction = $this->lastCorrection();
        $this->assertSame(['-2.0000', '5.0000', '10.0000'], [(string) $correction->qty_base, $correction->cost_unit?->getAmount(), $correction->cost_total?->getAmount()]);
        $this->assertSame([MorphMap::alias(StockCount::class), $count->id], [$correction->source_type, (int) $correction->source_id]);
        $this->assertSame(StocktakeService::LINE_KEY_PREFIX . $this->line($count)->id, $correction->idempotency_key);
        $this->assertSame(['8.0000', '5.0000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
        $this->assertSame(StockCountStatus::Completed, $count->status);
    }

    public function test_a_surplus_enters_at_the_purchase_price_otherwise_at_the_average(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '5.00');
        $this->variant->forceFill(['purchase_price' => '3.20'])->save();

        $this->stocktake->applyDifferences($this->countPhysical('12'));

        $correction = $this->lastCorrection();
        $this->assertSame(['2.0000', '3.2000', '6.4000'], [(string) $correction->qty_base, $correction->cost_unit?->getAmount(), $correction->cost_total?->getAmount()]);
        // (50 + 6,40) / 12 = 4,70
        $this->assertSame(['12.0000', '4.7000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);

        // Gegenprobe ohne Einkaufspreis: der Mehrbestand geht zum Durchschnitt ein, kein eigener Inventurpreis.
        $this->variant->forceFill(['purchase_price' => null])->save();
        $this->stocktake->applyDifferences($this->countPhysical('13'));
        $this->assertSame(['4.7000', '4.7000'], [$this->lastCorrection()->cost_unit?->getAmount(), $this->lastCorrection()->cost_total?->getAmount()]);
        $this->assertSame(['13.0000', '4.7000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_fifo_shortage_consumes_layers_and_a_surplus_adds_a_layer(): void {
        $this->useFifo();
        Carbon::setTestNow('2026-02-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '2', '1.00');
        Carbon::setTestNow('2026-03-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '5', '2.00');
        Carbon::setTestNow();

        $this->stocktake->applyDifferences($this->countPhysical('4'));

        // 2 × 1,00 + 1 × 2,00 — die älteste Schicht zuerst.
        $this->assertSame(['-3.0000', '4.0000'], [(string) $this->lastCorrection()->qty_base, $this->lastCorrection()->cost_total?->getAmount()]);
        $this->assertSame('8.0000', $this->layerValue());

        $this->variant->forceFill(['purchase_price' => '2.50'])->save();
        $this->stocktake->applyDifferences($this->countPhysical('6'));

        $correction = $this->lastCorrection();
        $layer = StockValuationLayer::query()->where('source_movement_id', $correction->id)->sole();
        $this->assertSame(['2.0000', '2.5000'], [bcadd((string) $layer->qty_remaining, '0', 4), $layer->unit_cost?->getAmount()]);
        $this->assertSame('13.0000', $this->layerValue());
        $this->assertFalse(StockValuation::query()->where('article_variant_id', $this->variant->id)->exists(), 'Das Durchschnittsbuch bleibt bei FIFO still.');
    }

    public function test_other_states_and_foreign_stock_stay_unvalued(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '3', '2.00');
        $ledger = app(InventoryLedger::class);
        $ledger->receipt($this->variant, $this->warehouse, '5', OwnershipType::Consignment);
        $ledger->reserve($this->variant, $this->warehouse, '1');

        $count = $this->stocktake->open($this->warehouse);
        $this->stocktake->recordCount($this->line($count, ownership: OwnershipType::Consignment), '4');
        $this->stocktake->recordCount($this->line($count, StockState::Reserved), '0');
        $this->stocktake->applyDifferences($count);

        $corrections = StockMovement::query()->where('movement_type', StockMovementType::Correction->value)->orderBy('id')->get();
        $this->assertCount(2, $corrections);
        foreach ($corrections as $correction) {
            $this->assertNull($correction->cost_total, 'Fremdbestand und Reservierung tragen keinen Wert.');
        }
        $this->assertSame(['3.0000', '2.0000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_each_correction_books_and_values_once(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '5.00');
        $count = $this->countPhysical('8');
        $this->stocktake->applyDifferences($count);

        try {
            $this->stocktake->applyDifferences($count->refresh());
            $this->fail('Eine abgeschlossene Inventur darf nicht erneut buchen.');
        } catch (RuntimeException) {
            // erwartet
        }
        $this->assertSame(1, StockMovement::query()->where('movement_type', StockMovementType::Correction->value)->count());
        $this->assertSame('8.0000', bcadd((string) $this->book()->qty_on_hand, '0', 4));

        // Direkt über den Baustein: derselbe Schlüssel bewegt das Buch nur einmal — Zugang wie Abgang.
        $corrections = app(StockCorrection::class);
        $surplus = new StockPosting($this->variant, $this->warehouse, StockState::Physical, '2', StockMovementType::Correction, idempotencyKey: 'korrektur-zugang');
        $first = $corrections->book($surplus, Money::of('3.00', CurrencyCode::Euro, 4));
        $again = $corrections->book($surplus, Money::of('3.00', CurrencyCode::Euro, 4));
        $this->assertSame($first->id, $again->id);
        $this->assertSame('10.0000', bcadd((string) $this->book()->qty_on_hand, '0', 4));

        $shortage = new StockPosting($this->variant, $this->warehouse, StockState::Physical, '-1', StockMovementType::Correction, idempotencyKey: 'korrektur-abgang');
        $corrections->book($shortage);
        $corrections->book($shortage);
        $this->assertSame('9.0000', bcadd((string) $this->book()->qty_on_hand, '0', 4));
    }

    public function test_four_eyes_measures_the_booked_value_and_rolls_back_when_it_blocks(): void {
        $this->useFifo();
        $this->organization->update(['settings' => ['inventory' => ['four_eyes' => ['count' => true, 'count_threshold' => 8]]]]);
        $counter = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $reviewer = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Carbon::setTestNow('2026-02-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '1', '1.00');
        Carbon::setTestNow('2026-03-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '5', '10.00');
        Carbon::setTestNow();
        $count = $this->countPhysical('4', $counter->id);

        // Die frühere Schätzung (nächste Schicht × Menge = 2,00) läge unter dem Grenzwert; gebucht werden 1,00 + 10,00.
        $this->assertSame('1.0000', app(FifoValuationService::class)->unitCost($this->variant, $this->warehouse));
        try {
            $this->stocktake->applyDifferences($count, $counter->id);
            $this->fail('Wer gezählt hat, gibt eine Differenz ab dem Grenzwert nicht frei.');
        } catch (RuntimeException $e) {
            $this->assertSame(__('inventory.count_ui.four_eyes_blocked'), $e->getMessage());
        }
        $this->assertSame(0, StockMovement::query()->where('movement_type', StockMovementType::Correction->value)->count());
        $this->assertSame('51.0000', $this->layerValue(), 'Die gesperrte Freigabe rollt den Verbrauch der Schichten zurück.');
        $this->assertSame(StockCountStatus::Counting, $count->fresh()?->status);
        $this->assertFalse((bool) $this->line($count)->applied);

        $this->stocktake->applyDifferences($count, $reviewer->id);

        $this->assertSame('11.0000', $this->lastCorrection()->cost_total?->getAmount());
        $changes = AuditLog::query()->where('event', 'inventory.countApproved')->sole()->changes ?? [];
        $this->assertSame([1, [$counter->id]], [$changes['flagged_lines'] ?? null, $changes['counted_by'] ?? null]);
    }

    public function test_compensating_an_issue_returns_it_at_its_own_cost(): void {
        app(ValuationService::class)->receipt($this->variant, $this->warehouse, '10', '4.00');
        $issue = app(ValuationService::class)->issue($this->variant, $this->warehouse, '3')->first();
        // Gegenprobe: ein Einkaufspreis bleibt außen vor — die Gegenbuchung kennt den Wert der Bewegung.
        $this->variant->forceFill(['purchase_price' => '9.99'])->save();

        $reversal = app(InventoryConflictResolver::class)->compensate($this->conflictFor($issue));

        $this->assertSame(['3.0000', '4.0000', '12.0000'], [(string) $reversal->qty_base, $reversal->cost_unit?->getAmount(), $reversal->cost_total?->getAmount()]);
        $this->assertSame([MorphMap::alias(StockMovement::class), $issue->id], [$reversal->source_type, (int) $reversal->source_id]);
        $this->assertSame(['10.0000', '4.0000'], [bcadd((string) $this->book()->qty_on_hand, '0', 4), $this->book()->avg_cost?->getAmount()]);
    }

    public function test_compensating_a_receipt_leaves_by_method(): void {
        $this->useFifo();
        Carbon::setTestNow('2026-02-01 08:00:00');
        app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '2', '1.00');
        Carbon::setTestNow('2026-03-01 08:00:00');
        $receipt = app(FifoValuationService::class)->receipt($this->variant, $this->warehouse, '3', '5.00');
        Carbon::setTestNow();

        $reversal = app(InventoryConflictResolver::class)->compensate($this->conflictFor($receipt));

        // Minderbestand nach Verfahren: die älteste Schicht zuerst (2 × 1,00 + 1 × 5,00), nicht die des Eingangs.
        $this->assertSame(['-3.0000', '7.0000'], [(string) $reversal->qty_base, $reversal->cost_total?->getAmount()]);
        $this->assertSame('10.0000', $this->layerValue());
    }
}
