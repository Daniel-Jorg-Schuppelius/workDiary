<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FinishedGoodReceiptValuationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Enums\Inventory\StockMovementType;
use App\Enums\TimeEntry\TimeEntryKind;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Inventory\{StockMovement, StockValuation, Warehouse};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureMaterialRequirement, ProcedureTemplateVersion};
use App\Models\Time\TimeEntry;
use App\Services\Inventory\{FifoValuationService, ValuationService};
use App\Services\Manufacturing\{ManufacturingInventoryService, ManufacturingOrderService, ManufacturingReportService};
use App\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1719: Der Eingang des Fertigerzeugnisses ist mit den Herstellkosten
 * bewertet — Materialverbrauch plus Arbeitszeit zum internen Satz je
 * Gutstück; Teilrückmeldungen buchen den Zuwachs, sodass die Summe der
 * Eingänge die Herstellkosten des Auftrags ergibt.
 */
final class FinishedGoodReceiptValuationTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $warehouse;

    private ArticleVariant $materialVariant;

    private Article $product;

    private ArticleVariant $productVariant;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $material = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->materialVariant = $this->variantFor($material);
        $version = ProcedureTemplateVersion::factory()->create();
        ProcedureMaterialRequirement::factory()->perUnit('2')->create([
            'procedure_template_version_id' => $version->id,
            'article_id' => $material->id,
        ]);
        $this->product = Article::factory()->create([
            'organization_id' => $this->organization->id,
            'default_procedure_template_version_id' => $version->id,
        ]);
        $this->productVariant = $this->variantFor($this->product);
    }

    public function test_the_finished_good_is_received_at_material_plus_labour_per_good_unit(): void {
        app(ValuationService::class)->receipt($this->materialVariant, $this->warehouse, '40', '3.00');
        $order = $this->releasedOrder('5');
        app(ManufacturingInventoryService::class)->consume($order->materials()->firstOrFail(), '10'); // 10 × 3,00 = 30,00
        $this->labour($order, 90, '20.00'); // 1,5 h × 20,00 = 30,00

        app(ManufacturingReportService::class)->report($order, '5', '5');

        $receipt = $this->receipts()->sole();
        $this->assertSame(['5.0000', '60.0000'], [(string) $receipt->qty_base, $receipt->cost_total?->getAmount()]);
        $this->assertSame([$order->getMorphClass(), (int) $order->id], [$receipt->source_type, (int) $receipt->source_id]);
        $book = StockValuation::query()->where('article_variant_id', $this->productVariant->id)->sole();
        $this->assertSame('12.0000', $book->avg_cost?->getAmount(), '60,00 Herstellkosten / 5 Gutstück');
    }

    public function test_partial_reports_book_the_increment_so_the_receipts_sum_to_the_cost_of_the_order(): void {
        $this->useFifo();
        app(FifoValuationService::class)->receipt($this->materialVariant, $this->warehouse, '40', '3.00');
        $order = $this->releasedOrder('10');
        $material = $order->materials()->firstOrFail();
        $reports = app(ManufacturingReportService::class);

        app(ManufacturingInventoryService::class)->consume($material, '8'); // 24,00
        $reports->report($order, '4', '4');
        app(ManufacturingInventoryService::class)->consume($material, '12'); // 36,00
        $this->labour($order, 60, '40.00'); // 40,00
        $reports->report($order, '6', '6');

        // 76,00 / 6 = 12,6667 je Stück (kaufmännisch), gebucht 76,0002.
        $this->assertSame([['4.0000', '24.0000'], ['6.0000', '76.0002']], $this->receipts()->map(
            fn (StockMovement $m): array => [(string) $m->qty_base, $m->cost_total?->getAmount()],
        )->all());
    }

    public function test_without_costs_the_receipt_is_valued_at_zero_instead_of_failing(): void {
        $order = $this->releasedOrder('2');

        app(ManufacturingReportService::class)->report($order, '2', '2');

        $this->assertSame([['2.0000', '0.0000']], $this->receipts()->map(
            fn (StockMovement $m): array => [(string) $m->qty_base, $m->cost_total?->getAmount() ?? '0.0000'],
        )->all());
    }

    private function variantFor(Article $article): ArticleVariant {
        return ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $article->id,
            'is_default' => true,
            'option_signature' => 'default-' . $article->id,
        ]);
    }

    private function useFifo(): void {
        app(SettingsRegistry::class)->setServiceManaged('inventory.valuation_method', 'fifo', $this->organization);
        $this->organization->refresh();
    }

    private function releasedOrder(string $targetQty): ManufacturingOrder {
        $orders = app(ManufacturingOrderService::class);
        $order = $orders->createDraft($this->organization, $this->product, $this->productVariant, $targetQty, 'Stk', ['warehouse_id' => $this->warehouse->id]);

        return $orders->release($order);
    }

    /** Der interne Satz ist ein Snapshot am Zeiteintrag; der Test setzt ihn fest, statt die Satzhierarchie nachzubauen. */
    private function labour(ManufacturingOrder $order, int $minutes, string $rate): void {
        $entry = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => User::factory()->create(['organization_id' => $this->organization->id])->id,
            'manufacturing_order_id' => $order->id,
            'date' => '2030-01-15',
            'started_at' => '2030-01-15 08:00:00',
            'ended_at' => \Carbon\CarbonImmutable::parse('2030-01-15 08:00:00')->addMinutes($minutes),
            'minutes' => $minutes,
            'kind' => TimeEntryKind::Work->value,
        ]);
        $entry->forceFill(['internal_rate' => $rate])->saveQuietly();
    }

    /** @return \Illuminate\Support\Collection<int, StockMovement> */
    private function receipts(): \Illuminate\Support\Collection {
        return StockMovement::query()
            ->where('article_variant_id', $this->productVariant->id)
            ->where('movement_type', StockMovementType::FinishedGoodReceipt->value)
            ->orderBy('id')->get();
    }
}
