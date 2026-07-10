<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : E7UiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Enums\Manufacturing\ProcurementMode;
use App\Models\{Article, ManufacturingOrder, Supplier, User, Warehouse, WorkCenter};
use App\Services\Manufacturing\ManufacturingOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * E7-Oberflächen (Feature 047/048): Fremdfertigungs-Button, Arbeitsplatz-Zuweisung
 * und Kapazitätsboard über HTTP.
 */
final class E7UiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true, 'base_unit' => 'Stk']);
    }

    private function draft(): ManufacturingOrder {
        return app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, null, '5', 'Stk', ['warehouse_id' => $this->warehouse->id],
        );
    }

    public function test_subcontract_button_creates_purchase_order(): void {
        $supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $order = $this->draft();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.subcontract', $order), [
            'supplier' => $supplier->sqid,
        ])->assertRedirect();

        $this->assertSame(ProcurementMode::Subcontract, $order->fresh()->procurement_mode);
        $this->assertNotNull($order->fresh()->subcontract_purchase_order_id);
    }

    public function test_assign_work_center(): void {
        $wc = WorkCenter::query()->create(['organization_id' => $this->organization->id, 'name' => 'Fräse', 'capacity_minutes' => 480]);
        $order = $this->draft();

        $this->actingAs($this->admin)->post(route('manufacturing-orders.work-center', $order), [
            'work_center' => $wc->sqid, 'minutes' => '120', 'day' => '2026-07-01',
        ])->assertRedirect();

        $this->assertSame($wc->id, $order->fresh()->work_center_id);
        $this->assertSame(120, $order->fresh()->planned_minutes);
    }

    public function test_capacity_board_and_create(): void {
        $this->actingAs($this->admin)->get(route('work-centers.index'))->assertOk();

        $this->actingAs($this->admin)->post(route('work-centers.store'), [
            'name' => 'Presse', 'capacity_minutes' => '600', 'setup_minutes' => '15',
        ])->assertRedirect();

        $this->assertSame(1, WorkCenter::query()->where('name', 'Presse')->count());
    }
}
