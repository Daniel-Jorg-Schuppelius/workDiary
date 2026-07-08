<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentFromDeliveryTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Enums\Shipping\ShipmentStatus;
use App\Models\{ArticleVariant, CarrierConnection, Customer, ManufacturingOrder, Shipment, StockDelivery, User, Warehouse};
use App\Services\Shipping\ShippingProviderRegistry;
use App\Services\Timeline\{DiaryEntryTimelineService, TimelineItem};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeShippingProvider;
use Tests\TestCase;

/**
 * Feature 059, MVP-128, Rang 20: Versandauftrag aus einer Auslieferung erzeugen
 * (Modal-Submit → Controller → ShipmentService), Empfänger aus dem Kunden,
 * idempotent, Zustellstatus in der Kunden-Fallakte.
 */
final class ShipmentFromDeliveryTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private FakeShippingProvider $provider;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        Storage::fake('local');

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $this->provider = new FakeShippingProvider('mock');
        app(ShippingProviderRegistry::class)->register($this->provider);

        CarrierConnection::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => 'mock',
            'name' => 'Mock-Carrier',
            'credentials' => ['username' => 'u', 'password' => 'p', 'api_key' => 'k'],
            'billing_number' => '3333333333',
            'sandbox' => true,
            'active' => true,
        ]);
    }

    private function customer(): Customer {
        return Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Muster GmbH',
            'company' => 'Muster GmbH',
            'address_street' => 'Teststr. 1',
            'address_zip' => '10115',
            'address_city' => 'Berlin',
            'country' => 'DE',
        ]);
    }

    /** @return array{0: ManufacturingOrder, 1: StockDelivery} */
    private function orderWithDelivery(?Customer $customer): array {
        $order = ManufacturingOrder::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create();
        $warehouse = Warehouse::factory()->create(['organization_id' => $this->organization->id]);
        $delivery = StockDelivery::query()->create([
            'organization_id' => $this->organization->id,
            'manufacturing_order_id' => $order->id,
            'article_variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer?->id,
            'quantity' => '1',
            'unit' => 'Stk',
            'name_snapshot' => 'Widget',
            'stock_status' => 'delivered',
            'facturation_status' => DeliveryFacturationStatus::Pending->value,
            'delivered_at' => now(),
        ]);

        return [$order, $delivery];
    }

    private function ship(ManufacturingOrder $order, StockDelivery $delivery, int $weight = 1500): \Illuminate\Testing\TestResponse {
        return $this->post(route('manufacturing-orders.deliveries.shipment', [$order, $delivery]), [
            'carrier' => 'mock',
            'weight_grams' => $weight,
        ]);
    }

    public function test_creates_shipment_and_label_from_delivery(): void {
        $customer = $this->customer();
        [$order, $delivery] = $this->orderWithDelivery($customer);

        $this->ship($order, $delivery)->assertRedirect();

        $shipment = Shipment::query()->where('stock_delivery_id', $delivery->id)->first();
        $this->assertNotNull($shipment);
        $this->assertSame(ShipmentStatus::Labeled, $shipment->status);
        $this->assertSame('TRACK-1', $shipment->tracking_number);
        $this->assertSame('Berlin', $shipment->recipient_snapshot['city'] ?? null);
        $this->assertSame('Muster GmbH', $shipment->recipient_snapshot['name'] ?? null);
        $this->assertNotNull($shipment->attachmentByMeta(Shipment::LABEL_META));
    }

    public function test_second_shipment_for_same_delivery_is_rejected(): void {
        $customer = $this->customer();
        [$order, $delivery] = $this->orderWithDelivery($customer);

        $this->ship($order, $delivery)->assertRedirect();
        $this->ship($order, $delivery)->assertRedirect()->assertSessionHas('error');

        $this->assertSame(1, Shipment::query()->where('stock_delivery_id', $delivery->id)->count());
        $this->assertSame(1, $this->provider->createCount);
    }

    public function test_delivery_without_customer_is_rejected(): void {
        [$order, $delivery] = $this->orderWithDelivery(null);

        $this->ship($order, $delivery)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_unknown_carrier_without_connection_is_rejected(): void {
        $customer = $this->customer();
        [$order, $delivery] = $this->orderWithDelivery($customer);

        $this->post(route('manufacturing-orders.deliveries.shipment', [$order, $delivery]), [
            'carrier' => 'dhl', // keine aktive Anbindung
            'weight_grams' => 1000,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, Shipment::query()->count());
    }

    public function test_shipment_appears_in_customer_timeline(): void {
        $customer = $this->customer();
        [$order, $delivery] = $this->orderWithDelivery($customer);
        $this->ship($order, $delivery)->assertRedirect();

        $result = app(DiaryEntryTimelineService::class)->forCustomer($customer, $this->admin);
        /** @var list<TimelineItem> $items */
        $items = $result['items'];
        $shipmentItems = array_values(array_filter($items, fn (TimelineItem $i): bool => $i->type === 'shipment'));

        $this->assertNotEmpty($shipmentItems);
        $this->assertStringContainsString('TRACK-1', (string) $shipmentItems[0]->summary);
    }
}
