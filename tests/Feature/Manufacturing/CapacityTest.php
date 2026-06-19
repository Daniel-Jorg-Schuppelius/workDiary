<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CapacityTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, Warehouse, WorkCenter};
use App\Services\Manufacturing\{CapacityService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Kapazitätsplanung (Feature 047/048, E7): Tageslast eines Arbeitsplatzes inkl.
 * Rüstzeit je Auftrag und Überlastungserkennung.
 */
final class CapacityTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    public function test_load_sums_planned_minutes_with_setup_and_flags_overload(): void {
        $this->setUpOrganization();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $product = Article::factory()->create(['organization_id' => $this->organization->id, 'manufacturable' => true]);
        $workCenter = WorkCenter::query()->create([
            'organization_id' => $this->organization->id, 'name' => 'Säge', 'capacity_minutes' => 480, 'setup_minutes' => 30,
        ]);
        $day = Carbon::parse('2026-07-01');

        $capacity = app(CapacityService::class);
        $orders = app(ManufacturingOrderService::class);
        foreach (['A', 'B'] as $_) {
            $order = $orders->createDraft($this->organization, $product, null, '1', 'Stk', ['warehouse_id' => $warehouse->id]);
            $capacity->assign($order, $workCenter, 200, $day);
        }

        $load = $capacity->load($workCenter, $day);
        $this->assertSame(460, $load['planned']); // (200+30) × 2
        $this->assertSame(20, $load['free']);
        $this->assertFalse($load['overloaded']);

        $third = $orders->createDraft($this->organization, $product, null, '1', 'Stk', ['warehouse_id' => $warehouse->id]);
        $capacity->assign($third, $workCenter, 200, $day);

        $this->assertTrue($capacity->load($workCenter, $day)['overloaded']);
    }
}
