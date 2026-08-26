<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArticleCostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Models\{Article, ManufacturingOrder, ManufacturingOrderMaterial, ManufacturingOrderReport, TimeEntry, Warehouse};
use App\Services\Manufacturing\{ManufacturingCostingService, ManufacturingOrderService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachkalkulation je Artikel (Feature 047, MVP-715 — Vollscan G14): Aggregation
 * über abgeschlossene Aufträge im Zeitraum, Stückkosten Ø/min/max, Plan/Ist-
 * Abweichung, Zeitraumgrenzen, Reiter + CSV.
 */
final class ArticleCostingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Warehouse $warehouse;

    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true, 'name' => 'Schaltschrank S1']);
    }

    /**
     * Abgeschlossener Auftrag: Material Soll/Ist × Kostensatz, Gutmenge, Lohn.
     */
    private function completedOrder(string $targetQty, string $consumedQty, string $cost, string $good, string $scrap, int $laborMinutes, int $plannedMinutes, string $completedAt, ManufacturingOrderStatus $status = ManufacturingOrderStatus::Completed): ManufacturingOrder {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, $good, 'Stk', ['warehouse_id' => $this->warehouse->id, 'planned_minutes' => $plannedMinutes],
        );
        ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id, 'article_id' => $this->product->id,
            'name_snapshot' => 'Material', 'unit_snapshot' => 'Stk',
            'target_qty' => $targetQty, 'consumed_qty' => $consumedQty, 'cost_snapshot' => $cost, 'is_tool' => false,
        ]);
        ManufacturingOrderReport::query()->create([
            'manufacturing_order_id' => $order->id, 'produced_qty' => bcadd($good, $scrap, 4), 'good_qty' => $good, 'scrap_qty' => $scrap, 'reported_at' => Carbon::now(),
        ]);
        if ($laborMinutes > 0) {
            $te = TimeEntry::factory()->create([
                'organization_id' => $this->organization->id,
                'manufacturing_order_id' => $order->id,
                'minutes' => $laborMinutes,
            ]);
            // Interner Stundensatz direkt setzen (Observer würde ihn sonst neu berechnen).
            DB::table('time_entries')->where('id', $te->id)->update(['internal_rate' => 30, 'minutes' => $laborMinutes]);
        }
        // Statusmaschine umgehen: nur der Endzustand zählt für die Nachkalkulation.
        DB::table('manufacturing_orders')->where('id', $order->id)->update([
            'status' => $status->value,
            'completed_at' => $completedAt,
        ]);

        return $order->fresh() ?? $order;
    }

    public function test_costing_for_article_aggregates_completed_orders_in_period(): void {
        // Auftrag A: Plan 10×2 = 20, Ist 8×2 = 16, Lohn 2 h × 30 = 60 → 76 / 8 Stk = 9,5
        $this->completedOrder('10', '8', '2', '8', '0', 120, 100, '2026-06-05 10:00:00');
        // Auftrag B: Plan 10×2 = 20, Ist 12×2 = 24, kein Lohn → 24 / 4 Stk = 6, Ausschuss 1
        $this->completedOrder('10', '12', '2', '4', '1', 0, 50, '2026-06-20 10:00:00');
        // Außerhalb des Zeitraums bzw. nicht abgeschlossen: zählen nicht.
        $this->completedOrder('10', '10', '2', '10', '0', 0, 10, '2026-05-01 10:00:00');
        $this->completedOrder('10', '10', '2', '10', '0', 0, 10, '2026-06-10 10:00:00', ManufacturingOrderStatus::InProgress);

        $result = app(ManufacturingCostingService::class)->costingForArticle(
            (int) $this->product->id,
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
        );

        $this->assertSame(2, $result['order_count']);
        $this->assertSame('40.0000', $result['planned_material']);
        $this->assertSame('40.0000', $result['actual_material']);   // 16 + 24
        $this->assertSame('60.0000', $result['labor']);
        $this->assertSame('100.0000', $result['total']);           // 76 + 24
        $this->assertSame('12.0000', $result['good']);
        $this->assertSame('1.0000', $result['scrap']);
        $this->assertSame(150, $result['planned_minutes']);
        $this->assertSame(120, $result['actual_minutes']);
        $this->assertSame(-30, $result['minutes_deviation']);
        $this->assertSame('8.3333', $result['unit_cost_avg']);     // 100 / 12
        $this->assertSame('6.0000', $result['unit_cost_min']);
        $this->assertSame('9.5000', $result['unit_cost_max']);
        $this->assertSame('0.0000', $result['deviation_abs']);
        $this->assertSame('0.0', $result['deviation_pct']);

        [$a, $b] = $result['orders'];
        $this->assertSame('-4.0000', $a['deviation_abs']);
        $this->assertSame('-20.0', $a['deviation_pct']);
        $this->assertSame('4.0000', $b['deviation_abs']);
        $this->assertSame('20.0', $b['deviation_pct']);
        $this->assertSame('2026-06-05', $a['completed_at']?->toDateString());

        // Qualität über alle Rückmeldungen des Artikels (nicht zeitraumbegrenzt).
        $this->assertSame('1.0000', $result['quality']['scrap']);
    }

    public function test_costing_for_article_without_orders_is_empty_and_null_safe(): void {
        $result = app(ManufacturingCostingService::class)->costingForArticle(
            (int) $this->product->id,
            CarbonImmutable::parse('2026-06-01')->startOfDay(),
            CarbonImmutable::parse('2026-06-30')->endOfDay(),
        );

        $this->assertSame(0, $result['order_count']);
        $this->assertSame('0.0000', $result['unit_cost_avg']);
        $this->assertNull($result['unit_cost_min']);
        $this->assertNull($result['deviation_pct']);
    }

    public function test_costing_tab_renders_and_exports_csv(): void {
        $this->completedOrder('10', '8', '2', '8', '0', 120, 100, '2026-06-05 10:00:00');
        $admin = $this->orgAdmin();

        $page = $this->actingAs($admin)->get(route('articles.costing', [$this->product, 'from' => '2026-06-01', 'to' => '2026-06-30']));
        $page->assertOk();
        $page->assertSeeText(__('article.costing.title'));
        $page->assertSeeText('9,5000 €');

        // Reiter auf der Stammdaten-Seite verlinkt die Nachkalkulation.
        $show = $this->actingAs($admin)->get(route('articles.show', $this->product));
        $show->assertOk();
        $show->assertSee(route('articles.costing', $this->product), false);

        $csv = $this->actingAs($admin)->get(route('articles.costing', [$this->product, 'from' => '2026-06-01', 'to' => '2026-06-30', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $body = (string) $csv->getContent();
        $this->assertStringContainsString('StueckkostenEUR', $body);
        $this->assertStringContainsString('9.5000', $body);
        $this->assertStringContainsString(__('article.costing.sum'), $body);
    }
}
