<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingQualityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ManufacturingOrder, ManufacturingOrderReport, Warehouse};
use App\Services\Manufacturing\{ManufacturingOrderService, ManufacturingQualityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Qualitätskennzahlen der Fertigung (Feature 047/048, E7): Aggregation von
 * Gut/Ausschuss/Nacharbeit zu Yield, Ausschuss- und Nacharbeitsquote.
 */
final class ManufacturingQualityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Article $product;
    private ManufacturingOrder $order;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);

        $this->order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '15', 'Stk', ['warehouse_id' => $warehouse->id],
        );

        $this->report('10', '8', '1', '1');
        $this->report('5', '5', '0', '0');
    }

    public function test_metrics_for_order(): void {
        $m = app(ManufacturingQualityService::class)->metricsFor($this->order);

        $this->assertSame('15.0000', $m['produced']);
        $this->assertSame('13.0000', $m['good']);
        $this->assertSame('1.0000', $m['scrap']);
        $this->assertSame('0.8666', $m['yield']);      // 13 / 15
        $this->assertSame('0.0666', $m['scrap_rate']); // 1 / 15
    }

    public function test_metrics_aggregated_per_article(): void {
        $m = app(ManufacturingQualityService::class)->metricsForArticle((int) $this->product->id);

        $this->assertSame('15.0000', $m['produced']);
        $this->assertSame('13.0000', $m['good']);
    }

    private function report(string $produced, string $good, string $scrap, string $rework): void {
        ManufacturingOrderReport::query()->create([
            'manufacturing_order_id' => $this->order->id,
            'produced_qty' => $produced, 'good_qty' => $good, 'scrap_qty' => $scrap, 'rework_qty' => $rework,
            'reported_at' => Carbon::now(),
        ]);
    }
}
