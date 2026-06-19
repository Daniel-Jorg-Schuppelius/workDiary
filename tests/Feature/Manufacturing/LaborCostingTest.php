<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LaborCostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ManufacturingOrderMaterial, ManufacturingOrderReport, TimeEntry, Warehouse};
use App\Services\Manufacturing\{ManufacturingCostingService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Nachkalkulation inkl. Lohnkosten (Feature 047/048, E7): Ist-Material + Arbeitszeit
 * (Minuten × interner Stundensatz) ergeben die Gesamt- und Stückkosten.
 */
final class LaborCostingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_costing_includes_labor(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);

        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $product, null, '8', 'Stk', ['warehouse_id' => $warehouse->id],
        );

        ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id, 'article_id' => $product->id,
            'name_snapshot' => 'Material', 'unit_snapshot' => 'Stk',
            'target_qty' => '10', 'consumed_qty' => '8', 'cost_snapshot' => '2', 'is_tool' => false,
        ]);
        ManufacturingOrderReport::query()->create([
            'manufacturing_order_id' => $order->id, 'produced_qty' => '8', 'good_qty' => '8', 'reported_at' => Carbon::now(),
        ]);
        $te = TimeEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'manufacturing_order_id' => $order->id,
            'minutes' => 120,
        ]);
        // internalen Stundensatz direkt setzen (der TimeEntryObserver würde ihn
        // sonst aus dem Nutzer-/Projekt-Kostensatz neu berechnen).
        \Illuminate\Support\Facades\DB::table('time_entries')->where('id', $te->id)->update(['internal_rate' => 30, 'minutes' => 120]);

        $costing = app(ManufacturingCostingService::class)->costing($order);

        $this->assertSame('16.0000', $costing['actual']);   // 8 × 2 Material
        $this->assertSame('60.0000', $costing['labor']);     // 2 h × 30
        $this->assertSame('76.0000', $costing['total']);     // 16 + 60
        $this->assertSame('9.5000', $costing['unit_cost']);  // 76 / 8
    }
}
