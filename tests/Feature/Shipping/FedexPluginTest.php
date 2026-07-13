<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FedexPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, Shipment};
use App\Plugins\Contracts\{PluginCapability, ShippingProvider};
use App\Plugins\Fedex\FedexPlugin;
use App\Plugins\PluginDiscovery;
use App\Services\Shipping\{ShipmentPackage, ShipmentRecipient, ShipmentRequest, ShipmentService, ShippingProviderRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * FedEx-Carrier-Adapter (Feature 059, MVP-128 / Bauturbo A5): OAuth2-Token-Flow
 * (Client-Credentials im Form-Body) inkl. Cache + 401-Refresh, Label-Erzeugung
 * (PDF), Storno-Semantik (`output.cancelledShipment`), Tracking-Mapping und
 * Connection-Health-Fehlerzählung — alles über den Guzzle-MockHandler
 * ({@see FakePluginHttp}), nie gegen die echte API.
 */
class FedexPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TOKEN_URL = 'https://apis-sandbox.fedex.com/oauth/token';

    private const SHIP_URL = 'https://apis-sandbox.fedex.com/ship/v1/shipments';

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization([
            'settings' => [
                'einvoice' => [
                    'seller_name' => 'WorkDiary GmbH',
                    'street' => 'Werkstr. 1',
                    'zip' => '10115',
                    'city' => 'Berlin',
                    'country' => 'DE',
                ],
            ],
        ]);
    }

    private function connection(): CarrierConnection {
        return CarrierConnection::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => FedexPlugin::CARRIER,
            'name' => 'FedEx',
            'credentials' => ['username' => 'fedex-client-id', 'password' => 'fedex-client-secret'],
            'billing_number' => '740561073',
            'sandbox' => true,
            'active' => true,
        ]);
    }

    private function request(): ShipmentRequest {
        return new ShipmentRequest(
            new ShipmentRecipient('Erika Muster', 'Bahnhofstr. 5', '80331', 'München', 'DE', 'Erika Muster', null, '+49891234'),
            [new ShipmentPackage(2500, 30, 20, 15)],
            'ORDER-42',
        );
    }

    /** @return \GuzzleHttp\Psr7\Response */
    private static function tokenResponse(string $token = 'fedex-token-1') {
        return FakePluginHttp::response(['access_token' => $token, 'token_type' => 'bearer', 'expires_in' => 3600]);
    }

    /** @return \GuzzleHttp\Psr7\Response */
    private static function shipResponse(string $tracking = '794911710000', ?string $b64 = null) {
        return FakePluginHttp::response([
            'transactionId' => 'tx-1',
            'output' => ['transactionShipments' => [[
                'masterTrackingNumber' => $tracking,
                'pieceResponses' => [[
                    'trackingNumber' => $tracking,
                    'packageDocuments' => [[
                        'contentType' => 'LABEL',
                        'docType' => 'PDF',
                        'encodedLabel' => $b64 ?? base64_encode('%PDF-1.4 fedex label'),
                    ]],
                ]],
            ]]],
        ]);
    }

    public function test_plugin_is_discovered_and_advertises_shipping_capability(): void {
        $this->assertContains(FedexPlugin::class, PluginDiscovery::classes());

        $plugin = app(FedexPlugin::class);
        $this->assertInstanceOf(ShippingProvider::class, $plugin);
        $this->assertContains(PluginCapability::ShippingProvider, $plugin->capabilities());
        $this->assertSame('fedex', $plugin->carrier());
    }

    public function test_plugin_registers_itself_in_the_registry(): void {
        $provider = app(ShippingProviderRegistry::class)->for('fedex');

        $this->assertInstanceOf(FedexPlugin::class, $provider);
    }

    public function test_create_shipment_maps_shipper_recipient_and_parses_pdf_label(): void {
        $pdf = base64_encode('%PDF-1.4 fedex label');

        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => self::shipResponse(b64: $pdf),
        ]);

        $label = app(FedexPlugin::class)->createShipment($this->connection(), $this->request());

        $this->assertSame('794911710000', $label->trackingNumber);
        $this->assertSame('794911710000', $label->carrierShipmentId);
        $this->assertSame($pdf, $label->labelBase64);
        $this->assertSame('pdf', $label->format);

        $fake->assertSent(function (RequestInterface $request): bool {
            if (! str_contains((string) $request->getUri(), '/ship/v1/shipments')) {
                return false;
            }
            $body = json_decode((string) $request->getBody(), true);

            return data_get($body, 'accountNumber.value') === '740561073'
                && data_get($body, 'requestedShipment.shipper.contact.companyName') === 'WorkDiary GmbH'
                && data_get($body, 'requestedShipment.shipper.address.city') === 'Berlin'
                && data_get($body, 'requestedShipment.recipients.0.contact.personName') === 'Erika Muster'
                && data_get($body, 'requestedShipment.recipients.0.address.postalCode') === '80331'
                && data_get($body, 'requestedShipment.labelSpecification.imageType') === 'PDF'
                && (float) data_get($body, 'requestedShipment.requestedPackageLineItems.0.weight.value') === 2.5
                && data_get($body, 'requestedShipment.requestedPackageLineItems.0.dimensions.length') === 30
                && data_get($body, 'requestedShipment.requestedPackageLineItems.0.customerReferences.0.value') === 'ORDER-42'
                && data_get($body, 'labelResponseOptions') === 'LABEL';
        });

        // Token-Austausch: Client-Credentials im Form-Body (kein Basic).
        $fake->assertSent(function (RequestInterface $request): bool {
            $body = (string) $request->getBody();

            return str_contains((string) $request->getUri(), '/oauth/token')
                && str_contains($body, 'grant_type=client_credentials')
                && str_contains($body, 'client_id=fedex-client-id')
                && str_contains($body, 'client_secret=fedex-client-secret');
        });
    }

    public function test_access_token_is_cached_between_calls(): void {
        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => self::shipResponse(),
        ]);

        $plugin = app(FedexPlugin::class);
        $connection = $this->connection();
        $plugin->createShipment($connection, $this->request());
        $plugin->createShipment($connection, $this->request());

        $tokenCalls = array_filter(
            $fake->recorded(),
            static fn(array $entry): bool => str_contains((string) $entry['request']->getUri(), '/oauth/token'),
        );
        $this->assertCount(1, $tokenCalls, 'Der Access-Token muss zwischen Aufrufen gecacht werden.');
    }

    public function test_rejected_token_is_refreshed_once_after_401(): void {
        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => [self::tokenResponse('stale-token'), self::tokenResponse('fresh-token')],
            self::SHIP_URL => [FakePluginHttp::response(['errors' => [['code' => 'NOT.AUTHORIZED.ERROR']]], 401), self::shipResponse()],
        ]);

        $label = app(FedexPlugin::class)->createShipment($this->connection(), $this->request());

        $this->assertSame('794911710000', $label->carrierShipmentId);

        $fake->assertSent(function (RequestInterface $request): bool {
            return str_contains((string) $request->getUri(), '/ship/v1/shipments')
                && $request->getHeaderLine('Authorization') === 'Bearer fresh-token';
        });

        $tokenCalls = array_filter(
            $fake->recorded(),
            static fn(array $entry): bool => str_contains((string) $entry['request']->getUri(), '/oauth/token'),
        );
        $this->assertCount(2, $tokenCalls, 'Nach einem 401 muss genau einmal ein frischer Token geholt werden.');
    }

    public function test_create_shipment_failure_throws_and_counts_connection_failure(): void {
        Storage::fake('local');

        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => FakePluginHttp::response(['errors' => [['code' => 'SHIPMENT.VALIDATION.FAILED']]], 422),
        ]);

        $connection = $this->connection();
        app(ShippingProviderRegistry::class)->register(app(FedexPlugin::class));

        $shipment = Shipment::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => 'fedex',
            'status' => ShipmentStatus::Draft->value,
        ]);

        try {
            app(ShipmentService::class)->createLabel($shipment, $this->request());
            $this->fail('createLabel hätte werfen müssen.');
        } catch (RuntimeException) {
            // erwartet
        }

        $connection->refresh();
        $this->assertSame(1, (int) $connection->consecutive_failures);
        $this->assertNotNull($connection->last_error);
    }

    public function test_cancel_shipment_reflects_cancelled_flag(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://apis-sandbox.fedex.com/ship/v1/shipments/cancel' => FakePluginHttp::response([
                'output' => ['cancelledShipment' => true],
            ]),
        ]);

        $this->assertTrue(app(FedexPlugin::class)->cancelShipment($this->connection(), '794911710000'));
    }

    public function test_cancel_shipment_returns_false_when_not_cancelled(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://apis-sandbox.fedex.com/ship/v1/shipments/cancel' => FakePluginHttp::response([
                'output' => ['cancelledShipment' => false],
            ]),
        ]);

        $this->assertFalse(app(FedexPlugin::class)->cancelShipment($this->connection(), '794911710000'));
    }

    public function test_track_maps_status_and_events(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://apis-sandbox.fedex.com/track/v1/trackingnumbers' => FakePluginHttp::response([
                'output' => ['completeTrackResults' => [[
                    'trackingNumber' => '794911710000',
                    'trackResults' => [[
                        'latestStatusDetail' => ['code' => 'DL', 'derivedCode' => 'DL', 'description' => 'Delivered'],
                        'scanEvents' => [
                            [
                                'date' => '2026-07-10T09:30:00+02:00',
                                'eventDescription' => 'Delivered',
                                'scanLocation' => ['city' => 'München'],
                            ],
                            [
                                'date' => '2026-07-09T18:15:00+02:00',
                                'eventDescription' => 'In transit',
                                'scanLocation' => ['city' => 'Nürnberg'],
                            ],
                        ],
                    ]],
                ]]],
            ]),
        ]);

        $result = app(FedexPlugin::class)->track($this->connection(), '794911710000');

        $this->assertSame(ShipmentStatus::Delivered, $result->status);
        $this->assertCount(2, $result->events);
        $this->assertSame('Delivered', $result->events[0]->description);
        $this->assertSame('München', $result->events[0]->location);
    }

    public function test_track_maps_delivery_exception_to_problem(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://apis-sandbox.fedex.com/track/v1/trackingnumbers' => FakePluginHttp::response([
                'output' => ['completeTrackResults' => [[
                    'trackResults' => [[
                        'latestStatusDetail' => ['code' => 'DE', 'derivedCode' => 'DE', 'description' => 'Delivery exception'],
                        'scanEvents' => [],
                    ]],
                ]]],
            ]),
        ]);

        $result = app(FedexPlugin::class)->track($this->connection(), '794911710000');

        $this->assertSame(ShipmentStatus::Problem, $result->status);
    }
}
