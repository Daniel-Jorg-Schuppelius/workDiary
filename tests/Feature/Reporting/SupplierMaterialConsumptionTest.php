<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SupplierMaterialConsumptionTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Reporting;

use App\Enums\Manufacturing\ManufacturingOrderStatus;
use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\Article\{Article, ArticleSupply};
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\{ManufacturingOrder, ManufacturingOrderMaterial};
use App\Models\Procurement\{PurchaseOrder, PurchaseOrderLine};
use App\Models\Supplier\Supplier;
use App\Services\Reporting\SupplierAnalysisReportBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-948: Fertigungsverbrauch zählt mit, Lieferant aus dem tatsächlichen Einkauf. */
final class SupplierMaterialConsumptionTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->actingAs($this->orgAdmin());
    }

    private function consume(Article $article, string $qty, string $cost, ?string $completedAt): void {
        $order = ManufacturingOrder::factory()->create([
            'organization_id' => $this->organization->id,
            'number' => 'FA-' . $qty . '-' . ($completedAt ?? 'x'),
            'status' => $completedAt !== null ? ManufacturingOrderStatus::Completed->value : ManufacturingOrderStatus::InProgress->value,
            'completed_at' => $completedAt,
        ]);
        ManufacturingOrderMaterial::query()->create([
            'manufacturing_order_id' => $order->id, 'article_id' => $article->id, 'name_snapshot' => $article->name,
            'target_qty' => $qty, 'consumed_qty' => $qty, 'unit_snapshot' => 'kg', 'actual_cost' => $cost,
        ]);
    }

    public function test_manufacturing_consumption_is_attributed_to_the_actual_purchase_supplier(): void {
        $steel = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Stahlblech']);
        $listed = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Stammlieferant']);
        $bought = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Tatsächlicher Lieferant']);
        ArticleSupply::query()->create(['organization_id' => $this->organization->id, 'article_id' => $steel->id, 'supplier_id' => $listed->id, 'is_preferred' => true]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $purchase = PurchaseOrder::create(['organization_id' => $this->organization->id, 'number' => 'BE-0001', 'supplier_id' => $bought->id, 'warehouse_id' => $warehouse->id, 'status' => PurchaseOrderStatus::Received->value, 'currency' => 'EUR']);
        PurchaseOrderLine::create(['organization_id' => $this->organization->id, 'purchase_order_id' => $purchase->id, 'article_id' => $steel->id, 'description' => 'Blech', 'ordered_qty' => '100', 'received_qty' => '100', 'unit' => 'kg', 'unit_price' => '2.00', 'currency' => 'EUR']);

        $this->consume($steel, '12', '24.00', '2026-06-15 10:00:00');
        $this->consume($steel, '5', '10.00', '2026-07-02 10:00:00');
        $this->consume($steel, '7', '14.00', null);

        $result = app(SupplierAnalysisReportBuilder::class)->materialUsageBySupplier(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame('Tatsächlicher Lieferant', $row['supplierName']);
        $this->assertSame(1, $row['manufacturing']);
        $this->assertEqualsWithDelta(24.0, $row['value'], 0.001);
        $this->assertEqualsWithDelta(12.0, $row['quantities']['kg'], 0.001);
    }

    public function test_without_purchase_the_preferred_supply_applies(): void {
        $glue = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Kleber']);
        $listed = Supplier::create(['organization_id' => $this->organization->id, 'name' => 'Klebstoffhandel']);
        ArticleSupply::query()->create(['organization_id' => $this->organization->id, 'article_id' => $glue->id, 'supplier_id' => $listed->id, 'is_preferred' => true]);
        $this->consume($glue, '3', '9.00', '2026-06-20 08:00:00');

        $result = app(SupplierAnalysisReportBuilder::class)->materialUsageBySupplier(CarbonImmutable::parse('2026-06-01'), CarbonImmutable::parse('2026-06-30'));

        $this->assertSame('Klebstoffhandel', $result['rows'][0]['supplierName']);
    }
}
