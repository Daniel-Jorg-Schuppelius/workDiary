<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingCostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ManufacturingOrderMaterial, ManufacturingOrderReport, Warehouse};
use App\Services\Manufacturing\{ManufacturingCostingService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachkalkulation (Feature 047/048, E7): Plan- vs. Ist-Materialkosten und
 * Stückkosten je Gutmenge.
 */
final class ManufacturingCostingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_planned_vs_actual_and_unit_cost(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);
        $material = Article::factory()->create(['organization_id' => $this->organization->id]);

        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $product, null, '8', 'Stk', ['warehouse_id' => $warehouse->id],
        );

        ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id, 'article_id' => $material->id,
            'name_snapshot' => 'Material', 'unit_snapshot' => 'Stk',
            'target_qty' => '10', 'consumed_qty' => '8', 'cost_snapshot' => '2', 'is_tool' => false,
        ]);
        ManufacturingOrderReport::query()->create([
            'manufacturing_order_id' => $order->id, 'produced_qty' => '8', 'good_qty' => '8', 'reported_at' => Carbon::now(),
        ]);

        $costing = app(ManufacturingCostingService::class)->costing($order);

        $this->assertSame('20.0000', $costing['planned']);   // 10 × 2
        $this->assertSame('16.0000', $costing['actual']);     // 8 × 2
        $this->assertSame('8.0000', $costing['good']);
        $this->assertSame('2.0000', $costing['unit_cost']);   // 16 / 8
    }
}
