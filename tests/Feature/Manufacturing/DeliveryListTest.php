<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DeliveryListTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\Article\{Article, ArticleVariant};
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, Warehouse};
use App\Models\Platform\User;
use App\Models\Shipping\Shipment;
use App\Services\Inventory\InventoryLedger;
use App\Services\Manufacturing\{DeliveryService, ManufacturingOrderService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1013: Lieferscheinliste mit Versandfilter und Suche. */
final class DeliveryListTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private function delivery(string $customerName, string $articleName): StockDelivery {
        $article = Article::factory()->create(['organization_id' => $this->organization->id, 'name' => $articleName]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id, 'article_id' => $article->id, 'is_default' => true, 'option_signature' => 'default-' . $article->id]);
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $order = app(ManufacturingOrderService::class)->createDraft($this->organization, $article, $variant, '5', 'Stk', ['warehouse_id' => $warehouse->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => $customerName, 'company' => $customerName]);
        app(InventoryLedger::class)->receipt($variant, $warehouse, '10');

        return app(DeliveryService::class)->deliver($variant, $warehouse, '3', $order, $customer);
    }

    public function test_the_list_shows_deliveries_with_shipping_filter_and_search(): void {
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $shipped = $this->delivery('Nordwind GmbH', 'Schaltschrank');
        $open = $this->delivery('Südhang AG', 'Steuergerät');
        Shipment::query()->create(['organization_id' => $this->organization->id, 'stock_delivery_id' => $shipped->id, 'carrier' => 'dhl', 'status' => ShipmentStatus::Labeled->value, 'tracking_number' => 'TRK-1']);

        $number = static fn (StockDelivery $delivery): string => 'LS-' . str_pad((string) $delivery->id, 6, '0', STR_PAD_LEFT);
        $this->actingAs($admin)->get(route('deliveries.index'))->assertOk()
            ->assertSee($number($shipped))->assertSee($number($open))->assertSee('TRK-1')
            ->assertSee(route('manufacturing-orders.deliveries.customs.form', [$open->order, $open]), false);
        $this->actingAs($admin)->get(route('deliveries.index', ['shipping' => 'open']))->assertOk()
            ->assertSee($number($open))->assertDontSee($number($shipped));
        $this->actingAs($admin)->get(route('deliveries.index', ['shipping' => 'shipped']))->assertOk()
            ->assertSee($number($shipped))->assertDontSee($number($open));
        $this->actingAs($admin)->get(route('deliveries.index', ['q' => 'Südhang']))->assertOk()
            ->assertSee($number($open))->assertDontSee($number($shipped));
    }
}
