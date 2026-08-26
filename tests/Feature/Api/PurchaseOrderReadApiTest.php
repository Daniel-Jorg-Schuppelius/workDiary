<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderReadApiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, Organization, PurchaseOrder, Supplier, User, Warehouse};
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-718 (Vollscan J11): Read-only-REST Bestellungen. */
final class PurchaseOrderReadApiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private Supplier $supplier;

    private Warehouse $warehouse;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->orgAdmin();
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Metall AG']);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function order(bool $submit = false): PurchaseOrder {
        $service = app(PurchaseOrderService::class);
        $order = $service->createDraft($this->organization, $this->supplier, $this->warehouse);
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Blech']);
        $service->addLine($order, $article, '3', ['unit_price' => '12.5000']);

        return $submit ? $service->submit($order) : $order;
    }

    public function test_missing_ability_is_forbidden(): void {
        Sanctum::actingAs($this->admin, ['articles:read']);

        $this->getJson(route('api.purchase-orders.index'))->assertForbidden();
    }

    public function test_index_filters_by_status_and_supplier_with_pagination(): void {
        $this->order();
        $ordered = $this->order(true);
        Sanctum::actingAs($this->admin, ['purchase_orders:read']);

        $page = $this->getJson(route('api.purchase-orders.index', ['per_page' => 1]))->assertOk();
        $this->assertCount(1, $page->json('data'));
        $this->assertSame(2, $page->json('meta.total'));

        $byStatus = $this->getJson(route('api.purchase-orders.index', ['status' => PurchaseOrderStatus::Ordered->value]))->assertOk();
        $this->assertCount(1, $byStatus->json('data'));
        $this->assertSame($ordered->sqid, $byStatus->json('data.0.id'));
        $this->assertSame('Metall AG', $byStatus->json('data.0.supplier.name'));

        $bySupplier = $this->getJson(route('api.purchase-orders.index', ['supplier' => $this->supplier->sqid]))->assertOk();
        $this->assertCount(2, $bySupplier->json('data'));
        $this->getJson(route('api.purchase-orders.index', ['supplier' => 'nix']))->assertNotFound();
    }

    public function test_show_includes_lines_with_sqids(): void {
        $order = $this->order();
        Sanctum::actingAs($this->admin, ['purchase_orders:read']);

        $response = $this->getJson(route('api.purchase-orders.show', $order))->assertOk();
        $response->assertJsonPath('data.id', $order->sqid)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.supplier.id', $this->supplier->sqid)
            ->assertJsonPath('data.lines.0.ordered_qty', '3.0000')
            ->assertJsonPath('data.lines.0.open_qty', '3.0000')
            ->assertJsonPath('data.lines.0.unit_price', '12.5000');
        $this->assertNotEmpty($response->json('data.lines.0.article_id'));
    }

    public function test_foreign_organization_order_is_not_found(): void {
        $other = Organization::factory()->create();
        $foreign = PurchaseOrder::query()->create([
            'organization_id' => $other->id,
            'number' => 'BE-X-1',
            'supplier_id' => Supplier::factory()->create(['organization_id' => $other->id])->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $other->id])->id,
            'status' => PurchaseOrderStatus::Draft->value,
            'currency' => 'EUR',
        ]);
        Sanctum::actingAs($this->admin, ['purchase_orders:read']);

        $this->getJson(route('api.purchase-orders.show', $foreign))->assertNotFound();
        $this->assertCount(0, $this->getJson(route('api.purchase-orders.index'))->json('data'));
    }
}
