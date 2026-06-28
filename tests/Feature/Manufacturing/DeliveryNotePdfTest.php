<?php
/*
 * Created on   : Fri Jun 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryNotePdfTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Manufacturing;

use App\Models\{Article, ArticleVariant, Customer, StockDelivery, User, Warehouse};
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-074: Auslieferung als Lieferschein-PDF (reiner Übergabenachweis).
 */
final class DeliveryNotePdfTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;
    private Warehouse $warehouse;
    private ArticleVariant $variant;
    private Article $product;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->seed(PermissionsSeeder::class);
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);

        $this->warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $this->product = Article::factory()->create(['organization_id' => $this->organization->id]);
        $this->variant = ArticleVariant::factory()->create([
            'organization_id' => $this->organization->id,
            'article_id' => $this->product->id,
            'is_default' => true,
            'option_signature' => 'default-' . $this->product->id,
        ]);
    }

    private function deliveryForOrder(): StockDelivery {
        $order = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, $this->variant, '5', 'Stk',
            ['warehouse_id' => $this->warehouse->id],
        );
        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'address_city' => 'Berlin',
        ]);

        app(InventoryLedger::class)->receipt($this->variant, $this->warehouse, '10');

        return app(DeliveryService::class)->deliver($this->variant, $this->warehouse, '3', $order, $customer);
    }

    public function test_delivery_note_pdf_is_streamed(): void {
        $delivery = $this->deliveryForOrder();

        $response = $this->actingAs($this->admin)
            ->get(route('manufacturing-orders.deliveries.pdf', [$delivery->order, $delivery]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_pdf_rejects_delivery_of_other_order(): void {
        $delivery = $this->deliveryForOrder();
        $otherOrder = app(ManufacturingOrderService::class)->createDraft(
            $this->organization, $this->product, $this->variant, '1', 'Stk',
            ['warehouse_id' => $this->warehouse->id],
        );

        $this->actingAs($this->admin)
            ->get(route('manufacturing-orders.deliveries.pdf', [$otherOrder, $delivery]))
            ->assertNotFound();
    }
}
