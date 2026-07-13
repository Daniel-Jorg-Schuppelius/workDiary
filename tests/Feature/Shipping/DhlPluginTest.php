<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DhlPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\CarrierConnection;
use App\Plugins\Contracts\{PluginCapability, ShippingProvider};
use App\Plugins\Dhl\DhlPlugin;
use App\Plugins\PluginDiscovery;
use App\Services\Shipping\{ShipmentPackage, ShipmentRecipient, ShipmentRequest, ShippingProviderRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class DhlPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
    }

    private function connection(): CarrierConnection {
        return CarrierConnection::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => DhlPlugin::CARRIER,
            'name' => 'DHL',
            'credentials' => ['username' => 'gk-user', 'password' => 'gk-pass', 'api_key' => 'api-key-123'],
            'billing_number' => '33333333330102',
            'sandbox' => true,
            'active' => true,
        ]);
    }

    private function request(): ShipmentRequest {
        return new ShipmentRequest(
            new ShipmentRecipient('Erika Muster', 'Bahnhofstr. 5', '80331', 'München', 'DE'),
            [new ShipmentPackage(2000)],
            'ORDER-42',
        );
    }

    public function test_plugin_is_discovered_and_advertises_shipping_capability(): void {
        $this->assertContains(DhlPlugin::class, PluginDiscovery::classes());

        $plugin = app(DhlPlugin::class);
        $this->assertInstanceOf(ShippingProvider::class, $plugin);
        $this->assertContains(PluginCapability::ShippingProvider, $plugin->capabilities());
        $this->assertSame('dhl', $plugin->carrier());
    }

    public function test_plugin_registers_itself_in_the_registry(): void {
        $provider = app(ShippingProviderRegistry::class)->for('dhl');

        $this->assertInstanceOf(DhlPlugin::class, $provider);
    }

    public function test_create_shipment_maps_recipient_and_parses_label(): void {
        $pdf = base64_encode('%PDF-1.4 dhl label');

        $fake = FakePluginHttp::fake([
            'https://api-sandbox.dhl.com/parcel/de/shipping/v2/orders*' => FakePluginHttp::response([
                'items' => [[
                    'shipmentNo' => '00340433339300000001',
                    'label' => ['b64' => $pdf, 'fileFormat' => 'PDF'],
                ]],
            ], 200),
        ]);

        $label = app(DhlPlugin::class)->createShipment($this->connection(), $this->request());

        $this->assertSame('00340433339300000001', $label->trackingNumber);
        $this->assertSame('00340433339300000001', $label->carrierShipmentId);
        $this->assertSame($pdf, $label->labelBase64);
        $this->assertSame('pdf', $label->format);

        $fake->assertSent(function (RequestInterface $request): bool {
            if (! str_contains((string) $request->getUri(), '/parcel/de/shipping/v2/orders')) {
                return false;
            }
            $body = json_decode((string) $request->getBody(), true);

            return data_get($body, 'shipments.0.consignee.name1') === 'Erika Muster'
                && data_get($body, 'shipments.0.consignee.postalCode') === '80331'
                && data_get($body, 'shipments.0.consignee.country') === 'DE'
                && (int) data_get($body, 'shipments.0.details.weight.value') === 2000
                && data_get($body, 'shipments.0.refNo') === 'ORDER-42';
        });
    }

    public function test_track_maps_status_and_events(): void {
        FakePluginHttp::fake([
            'https://api-sandbox.dhl.com/track/shipments*' => FakePluginHttp::response([
                'shipments' => [[
                    'status' => ['statusCode' => 'delivered', 'status' => 'Delivered'],
                    'events' => [[
                        'timestamp' => '2026-07-05T08:00:00',
                        'description' => 'Zustellung erfolgreich',
                        'location' => ['address' => ['addressLocality' => 'München']],
                    ]],
                ]],
            ], 200),
        ]);

        $result = app(DhlPlugin::class)->track($this->connection(), '00340433339300000001');

        $this->assertSame(ShipmentStatus::Delivered, $result->status);
        $this->assertCount(1, $result->events);
        $this->assertSame('Zustellung erfolgreich', $result->events[0]->description);
        $this->assertSame('München', $result->events[0]->location);
    }

    public function test_track_maps_failure_to_problem(): void {
        FakePluginHttp::fake([
            'https://api-sandbox.dhl.com/track/shipments*' => FakePluginHttp::response([
                'shipments' => [[
                    'status' => ['statusCode' => 'failure', 'status' => 'Delivery failed'],
                    'events' => [],
                ]],
            ], 200),
        ]);

        $result = app(DhlPlugin::class)->track($this->connection(), '00340433339300000001');

        $this->assertSame(ShipmentStatus::Problem, $result->status);
    }

    public function test_cancel_shipment_returns_true_on_success(): void {
        FakePluginHttp::fake([
            'https://api-sandbox.dhl.com/parcel/de/shipping/v2/orders*' => FakePluginHttp::response([], 200),
        ]);

        $ok = app(DhlPlugin::class)->cancelShipment($this->connection(), '00340433339300000001');

        $this->assertTrue($ok);
    }
}
