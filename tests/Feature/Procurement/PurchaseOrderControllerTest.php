<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseOrderControllerTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Procurement;

use App\Enums\Procurement\PurchaseOrderStatus;
use App\Models\{Article, ArticleVariant, PurchaseOrder, Supplier, User, Warehouse};
use App\Services\Procurement\{GoodsReceiptService, PurchaseOrderService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Beschaffungs-UI (Feature 048, E4): Berechtigungen, Anlage, Bestellen und
 * Wareneingang gegen die Bestellzeile über HTTP.
 */
final class PurchaseOrderControllerTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Supplier $supplier;
    private Warehouse $warehouse;
    private Article $article;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->supplier = Supplier::factory()->create(['organization_id' => $this->organization->id]);
        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->article = Article::factory()->create(['organization_id' => $this->organization->id, 'purchasable' => true, 'base_unit' => 'Stk']);
        ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id, 'article_id' => $this->article->id,
            'is_default' => true, 'option_signature' => 'default',
        ]);
    }

    public function test_index_requires_permission(): void {
        $stranger = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($stranger)->get(route('purchase-orders.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('purchase-orders.index'))->assertOk();
    }

    public function test_store_creates_draft(): void {
        $response = $this->actingAs($this->admin)->post(route('purchase-orders.store'), [
            'supplier' => $this->supplier->sqid,
            'warehouse' => $this->warehouse->sqid,
        ]);

        $order = PurchaseOrder::query()->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $order));
        $this->assertSame(PurchaseOrderStatus::Draft, $order->status);
        $this->assertStringStartsWith('BE-', $order->number);
    }

    public function test_add_line_submit_and_receive_flow(): void {
        $order = app(PurchaseOrderService::class)->createDraft($this->organization, $this->supplier, $this->warehouse);

        $this->actingAs($this->admin)->post(route('purchase-orders.lines.add', $order), [
            'article' => $this->article->sqid, 'qty' => '10', 'unit_price' => '2',
        ])->assertRedirect();
        $line = $order->lines()->firstOrFail();
        $this->assertSame('10.0000', $line->ordered_qty);

        $this->actingAs($this->admin)->post(route('purchase-orders.submit', $order))->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Ordered, $order->fresh()->status);

        $this->actingAs($this->admin)->post(route('purchase-orders.receive', $order), [
            'line' => $line->sqid, 'qty' => '10',
        ])->assertRedirect();
        $this->assertSame(PurchaseOrderStatus::Received, $order->fresh()->status);
        $this->assertSame('10.0000', $line->fresh()->received_qty);
    }

    public function test_suggestions_page_renders(): void {
        $this->actingAs($this->admin)->get(route('purchase-orders.suggestions'))->assertOk();
    }

    public function test_incoming_lists_open_lines(): void {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->createDraft($this->organization, $this->supplier, $this->warehouse);
        $line = $orders->addLine($po, $this->article, '10');
        $orders->submit($po);
        app(GoodsReceiptService::class)->receive($line, '4'); // Teil → offen 6

        $this->actingAs($this->admin)->get(route('purchase-orders.incoming'))->assertOk()->assertSee($po->number);
    }
}
