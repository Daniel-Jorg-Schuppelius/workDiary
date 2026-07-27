<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SubcontractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ProcurementMode;
use App\Models\{Article, Supplier, Warehouse};
use App\Services\Manufacturing\{ManufacturingOrderService, SubcontractService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Fremdfertigung (Feature 047/048, E7): Vergabe eines Fertigungsauftrags als
 * Lieferantenauftrag (E4) inkl. Verknüpfung und Beschaffungsart.
 */
final class SubcontractTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_commission_creates_linked_purchase_order(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true, 'base_unit' => 'Stk']);

        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $product, null, '5', 'Stk', ['warehouse_id' => $warehouse->id],
        );

        $po = app(SubcontractService::class)->commission($order, $supplier);

        $this->assertSame($supplier->id, $po->supplier_id);
        $line = $po->lines()->firstOrFail();
        $this->assertSame($product->id, $line->article_id);
        $this->assertSame('5.0000', $line->ordered_qty?->getNumericValue());

        $fresh = $order->fresh();
        $this->assertSame(ProcurementMode::Subcontract, $fresh->procurement_mode);
        $this->assertSame($po->id, $fresh->subcontract_purchase_order_id);
    }
}
