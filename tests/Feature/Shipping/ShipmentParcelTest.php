<?php
/*
 * Created on   : Fri Sep 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentParcelTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Shipping;

use App\Enums\Manufacturing\DeliveryFacturationStatus;
use App\Models\Article\ArticleVariant;
use App\Models\Customer\Customer;
use App\Models\Inventory\{StockDelivery, StockSerial, Warehouse};
use App\Models\Manufacturing\ManufacturingOrder;
use App\Models\Platform\User;
use App\Models\Shipping\{CarrierConnection, ShipmentParcel};
use App\Services\Inventory\SerialPassportService;
use App\Services\Shipping\ShippingProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeShippingProvider;
use Tests\TestCase;

/** MVP-900: Packstücke mit Seriennummern an der Auslieferung. */
final class ShipmentParcelTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    private ManufacturingOrder $order;

    private StockDelivery $delivery;

    /** @var list<StockSerial> */
    private array $serials = [];

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Muster GmbH', 'address_street' => 'Teststr. 1', 'address_zip' => '10115', 'address_city' => 'Berlin', 'country' => 'DE']);
        $this->order = ManufacturingOrder::factory()->create(['organization_id' => $this->organization->id]);
        $variant = ArticleVariant::factory()->create(['organization_id' => $this->organization->id]);
        $this->delivery = StockDelivery::query()->create([
            'organization_id' => $this->organization->id, 'manufacturing_order_id' => $this->order->id, 'article_variant_id' => $variant->id,
            'warehouse_id' => Warehouse::factory()->create(['organization_id' => $this->organization->id])->id, 'customer_id' => $customer->id,
            'quantity' => '3', 'unit' => 'Stk', 'name_snapshot' => 'Pumpe', 'stock_status' => 'delivered',
            'facturation_status' => DeliveryFacturationStatus::Pending->value, 'delivered_at' => now(),
        ]);
        foreach (['SN-1', 'SN-2', 'SN-3'] as $no) {
            $this->serials[] = StockSerial::factory()->create([
                'organization_id' => $this->organization->id, 'article_id' => $variant->article_id, 'article_variant_id' => $variant->id,
                'serial_no' => $no, 'stock_delivery_id' => $this->delivery->id,
            ]);
        }
    }

    private function saveParcel(int $weight, array $serials, ?ShipmentParcel $parcel = null): \Illuminate\Testing\TestResponse {
        $payload = ['weight_grams' => $weight, 'length_cm' => 40, 'width_cm' => 30, 'height_cm' => 20, 'serials' => array_map(fn (StockSerial $s): string => $s->sqid, $serials)];

        return $parcel === null
            ? $this->post(route('manufacturing-orders.deliveries.parcels.store', [$this->order, $this->delivery]), $payload)
            : $this->put(route('manufacturing-orders.deliveries.parcels.update', [$this->order, $this->delivery, $parcel]), $payload);
    }

    public function test_serials_are_assigned_once_and_parcels_feed_the_shipping_order(): void {
        $this->get(route('manufacturing-orders.deliveries.parcels.create', [$this->order, $this->delivery]))->assertOk()->assertSee('SN-1');
        $this->saveParcel(2000, [$this->serials[0], $this->serials[1]])->assertSessionHasNoErrors();
        $this->saveParcel(1500, [$this->serials[1]])->assertSessionHasErrors('serials');
        $this->saveParcel(1500, [$this->serials[2]])->assertSessionHasNoErrors();

        $first = ShipmentParcel::query()->where('position', 1)->firstOrFail();
        $this->assertSame(['SN-1', 'SN-2'], $first->serials()->orderBy('serial_no')->pluck('serial_no')->all());
        $this->get(route('manufacturing-orders.deliveries.parcels.edit', [$this->order, $this->delivery, $first]))->assertOk()->assertSee('SN-2')->assertDontSee('SN-3');
        $this->get(route('manufacturing-orders.show', $this->order))->assertOk()->assertSee(__('shipping.parcel.label', ['no' => 2, 'of' => 2]));

        $foreign = StockSerial::factory()->create(['organization_id' => $this->organization->id, 'serial_no' => 'SN-X']);
        $this->saveParcel(2000, [$this->serials[0], $foreign], $first)->assertSessionHasErrors('serials');

        $provider = new FakeShippingProvider('mock');
        app(ShippingProviderRegistry::class)->register($provider);
        CarrierConnection::query()->create(['organization_id' => $this->organization->id, 'carrier' => 'mock', 'name' => 'Mock', 'credentials' => ['username' => 'u', 'password' => 'p', 'api_key' => 'k'], 'billing_number' => '1', 'sandbox' => true, 'active' => true]);
        $this->post(route('manufacturing-orders.deliveries.shipment', [$this->order, $this->delivery]), ['carrier' => 'mock'])->assertSessionHasNoErrors();

        $this->assertSame([2000, 1500], array_map(fn ($p) => $p->weightGrams, $provider->lastRequest?->packages ?? []));
        $this->saveParcel(900, [], $first)->assertSessionHasErrors('weight_grams');
    }

    public function test_passport_shows_parcel_and_deleting_renumbers(): void {
        $this->saveParcel(2000, [$this->serials[0]]);
        $this->saveParcel(1000, [$this->serials[1]]);
        $this->delete(route('manufacturing-orders.deliveries.parcels.destroy', [$this->order, $this->delivery, ShipmentParcel::query()->where('position', 1)->firstOrFail()]))->assertRedirect();
        $this->assertSame([1], ShipmentParcel::query()->pluck('position')->all());

        $passports = app(SerialPassportService::class);
        $token = $passports->issue($this->organization);
        $passports->setEnabled($this->organization->fresh(), true);
        $this->get(route('serials.public-passport', ['token' => $token, 'serial' => 'SN-2']))->assertOk()->assertSee(__('shipping.parcel.label', ['no' => 1, 'of' => 1]));
    }
}
