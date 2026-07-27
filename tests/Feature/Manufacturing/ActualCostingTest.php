<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ActualCostingTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ArticleVariant, ManufacturingOrderMaterial, Warehouse};
use App\Services\Inventory\ValuationService;
use App\Services\Manufacturing\{ManufacturingCostingService, ManufacturingInventoryService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Echte Ist-Kosten (Feature 047/048, E7): der Verbrauch erfasst den
 * Bewertungs-Stückkostenwert; die Nachkalkulation nutzt die erfassten Ist-Kosten.
 */
final class ActualCostingTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_consume_captures_actual_cost_from_valuation(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);
        $materialArticle = Article::factory()->create(['organization_id' => $this->organization->id]);
        $materialVariant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $materialArticle->id,
            'is_default' => true, 'option_signature' => 'm',
        ]);

        // Bestand zum Stückpreis 2 (gleitender Durchschnitt).
        app(ValuationService::class)->receipt($materialVariant, $warehouse, '10', '2');

        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $product, null, '5', 'Stk', ['warehouse_id' => $warehouse->id],
        );
        $material = ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id, 'article_id' => $materialArticle->id,
            'article_variant_id' => $materialVariant->id, 'name_snapshot' => 'Material',
            'unit_snapshot' => 'Stk', 'target_qty' => '5', 'consumed_qty' => '0', 'is_tool' => false,
        ]);

        app(ManufacturingInventoryService::class)->consume($material, '5');

        $this->assertSame('5.0000', $material->fresh()->consumed_qty);
        $this->assertSame('10.0000', $material->fresh()->actual_cost?->getAmount()); // 5 × 2 (Ist)
        $this->assertSame('10.0000', app(ManufacturingCostingService::class)->costing($order)['actual']);
    }
}
