<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpsPluginTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, Organization, Shipment};
use App\Plugins\Contracts\{PluginCapability, ShippingProvider};
use App\Plugins\PluginDiscovery;
use App\Plugins\Ups\UpsPlugin;
use App\Services\Shipping\{CarrierTokenCache, ShipmentPackage, ShipmentRecipient, ShipmentRequest, ShipmentService, ShippingProviderRegistry};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

/**
 * UPS-Carrier-Adapter (Feature 059, MVP-128 / Bauturbo A5): OAuth2-Token-Flow
 * inkl. Cache + 401-Refresh, Label-Erzeugung (GIF!), Storno, Tracking-Mapping,
 * Org-Isolation des Token-Caches und Connection-Health-Fehlerzählung — alles
 * über den Guzzle-MockHandler ({@see FakePluginHttp}), nie gegen die echte API.
 */
class UpsPluginTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private const TOKEN_URL = 'https://wwwcie.ups.com/security/v1/oauth/token';

    private const SHIP_URL = 'https://wwwcie.ups.com/api/shipments/v2409/ship*';

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

    private function connection(?Organization $organization = null): CarrierConnection {
        return CarrierConnection::query()->create([
            'organization_id' => ($organization ?? $this->organization)->id,
            'carrier' => UpsPlugin::CARRIER,
            'name' => 'UPS',
            'credentials' => ['username' => 'ups-client-id', 'password' => 'ups-client-secret'],
            'billing_number' => 'A1B2C3',
            'sandbox' => true,
            'active' => true,
        ]);
    }

    private function request(): ShipmentRequest {
        return new ShipmentRequest(
            new ShipmentRecipient('Erika Muster', 'Bahnhofstr. 5', '80331', 'München', 'DE', 'Erika Muster', null, '+49891234'),
            [new ShipmentPackage(2000, 30, 20, 15)],
            'ORDER-42',
        );
    }

    /** @return \GuzzleHttp\Psr7\Response */
    private static function tokenResponse(string $token = 'token-1', int $expiresIn = 14399) {
        // UPS liefert expires_in als String.
        return FakePluginHttp::response(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => (string) $expiresIn]);
    }

    /** @return \GuzzleHttp\Psr7\Response */
    private static function shipResponse(string $shipmentNo = '1Z999AA10123456784', ?string $b64 = null) {
        return FakePluginHttp::response([
            'ShipmentResponse' => [
                'Response' => ['ResponseStatus' => ['Code' => '1', 'Description' => 'Success']],
                'ShipmentResults' => [
                    'ShipmentIdentificationNumber' => $shipmentNo,
                    'PackageResults' => [[
                        'TrackingNumber' => $shipmentNo,
                        'ShippingLabel' => ['ImageFormat' => ['Code' => 'GIF'], 'GraphicImage' => $b64 ?? base64_encode('GIF89a ups label')],
                    ]],
                ],
            ],
        ]);
    }

    public function test_plugin_is_discovered_and_advertises_shipping_capability(): void {
        $this->assertContains(UpsPlugin::class, PluginDiscovery::classes());

        $plugin = app(UpsPlugin::class);
        $this->assertInstanceOf(ShippingProvider::class, $plugin);
        $this->assertContains(PluginCapability::ShippingProvider, $plugin->capabilities());
        $this->assertSame('ups', $plugin->carrier());
    }

    public function test_plugin_registers_itself_in_the_registry(): void {
        $provider = app(ShippingProviderRegistry::class)->for('ups');

        $this->assertInstanceOf(UpsPlugin::class, $provider);
    }

    public function test_create_shipment_maps_shipper_recipient_and_parses_gif_label(): void {
        $gif = base64_encode('GIF89a ups label');

        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => self::shipResponse(b64: $gif),
        ]);

        $label = app(UpsPlugin::class)->createShipment($this->connection(), $this->request());

        $this->assertSame('1Z999AA10123456784', $label->trackingNumber);
        $this->assertSame('1Z999AA10123456784', $label->carrierShipmentId);
        $this->assertSame($gif, $label->labelBase64);
        $this->assertSame('gif', $label->format);

        $fake->assertSent(function (RequestInterface $request): bool {
            if (! str_contains((string) $request->getUri(), '/api/shipments/v2409/ship')) {
                return false;
            }
            $body = json_decode((string) $request->getBody(), true);

            return data_get($body, 'ShipmentRequest.Shipment.Shipper.Name') === 'WorkDiary GmbH'
                && data_get($body, 'ShipmentRequest.Shipment.Shipper.ShipperNumber') === 'A1B2C3'
                && data_get($body, 'ShipmentRequest.Shipment.Shipper.Address.City') === 'Berlin'
                && data_get($body, 'ShipmentRequest.Shipment.ShipTo.Name') === 'Erika Muster'
                && data_get($body, 'ShipmentRequest.Shipment.ShipTo.Address.PostalCode') === '80331'
                && data_get($body, 'ShipmentRequest.Shipment.PaymentInformation.ShipmentCharge.BillShipper.AccountNumber') === 'A1B2C3'
                && data_get($body, 'ShipmentRequest.Shipment.Package.0.PackageWeight.Weight') === '2.0'
                && data_get($body, 'ShipmentRequest.Shipment.Package.0.Dimensions.Length') === '30'
                && data_get($body, 'ShipmentRequest.LabelSpecification.LabelImageFormat.Code') === 'GIF'
                && data_get($body, 'ShipmentRequest.Shipment.ReferenceNumber.Value') === 'ORDER-42';
        });

        // Token-Austausch: Basic-Auth mit Client-ID/Secret + client_credentials.
        $fake->assertSent(function (RequestInterface $request): bool {
            return str_contains((string) $request->getUri(), '/security/v1/oauth/token')
                && str_contains($request->getHeaderLine('Authorization'), 'Basic ')
                && str_contains((string) $request->getBody(), 'grant_type=client_credentials');
        });
    }

    public function test_create_shipment_without_shipper_address_fails(): void {
        $this->organization->forceFill(['settings' => []])->save();
        $this->organization->refresh();

        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => self::shipResponse(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shipper address incomplete');

        app(UpsPlugin::class)->createShipment($this->connection(), $this->request());
    }

    public function test_access_token_is_cached_between_calls(): void {
        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => self::shipResponse(),
        ]);

        $plugin = app(UpsPlugin::class);
        $connection = $this->connection();
        $plugin->createShipment($connection, $this->request());
        $plugin->createShipment($connection, $this->request());

        $tokenCalls = array_filter(
            $fake->recorded(),
            static fn(array $entry): bool => str_contains((string) $entry['request']->getUri(), '/security/v1/oauth/token'),
        );
        $this->assertCount(1, $tokenCalls, 'Der Access-Token muss zwischen Aufrufen gecacht werden.');
    }

    public function test_rejected_token_is_refreshed_once_after_401(): void {
        $fake = FakePluginHttp::fake([
            self::TOKEN_URL => [self::tokenResponse('stale-token'), self::tokenResponse('fresh-token')],
            self::SHIP_URL => [FakePluginHttp::response(['error' => 'invalid token'], 401), self::shipResponse()],
        ]);

        $label = app(UpsPlugin::class)->createShipment($this->connection(), $this->request());

        $this->assertSame('1Z999AA10123456784', $label->carrierShipmentId);

        $fake->assertSent(function (RequestInterface $request): bool {
            return str_contains((string) $request->getUri(), '/api/shipments/')
                && $request->getHeaderLine('Authorization') === 'Bearer fresh-token';
        });

        $tokenCalls = array_filter(
            $fake->recorded(),
            static fn(array $entry): bool => str_contains((string) $entry['request']->getUri(), '/security/v1/oauth/token'),
        );
        $this->assertCount(2, $tokenCalls, 'Nach einem 401 muss genau einmal ein frischer Token geholt werden.');
    }

    public function test_token_cache_is_isolated_per_organization(): void {
        $cache = app(CarrierTokenCache::class);

        $connectionA = $this->connection();
        $otherOrg = Organization::factory()->create();
        $connectionB = CarrierConnection::withoutGlobalScopes()->create([
            'organization_id' => $otherOrg->id,
            'carrier' => UpsPlugin::CARRIER,
            'name' => 'UPS B',
            'credentials' => ['username' => 'other-id', 'password' => 'other-secret'],
            'sandbox' => true,
            'active' => true,
        ]);

        $fetches = 0;
        $fetch = function () use (&$fetches): array {
            $fetches++;

            return ['access_token' => 'token-org-' . $fetches, 'expires_in' => 3600];
        };

        $tokenA = $cache->remember($connectionA, $fetch);
        $tokenB = $cache->remember($connectionB, $fetch);

        $this->assertSame(2, $fetches, 'Jede Organisation braucht ihren eigenen Token.');
        $this->assertNotSame($tokenA, $tokenB);

        // Wiederholung je Verbindung trifft den Cache (kein weiterer Austausch).
        $this->assertSame($tokenA, $cache->remember($connectionA, $fetch));
        $this->assertSame($tokenB, $cache->remember($connectionB, $fetch));
        $this->assertSame(2, $fetches);
    }

    public function test_create_shipment_failure_throws_and_counts_connection_failure(): void {
        Storage::fake('local');

        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            self::SHIP_URL => FakePluginHttp::response(['response' => ['errors' => [['code' => '120100']]]], 400),
        ]);

        $connection = $this->connection();
        app(ShippingProviderRegistry::class)->register(app(UpsPlugin::class));

        $shipment = Shipment::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => 'ups',
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

    public function test_void_shipment_returns_true_on_success(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://wwwcie.ups.com/api/shipments/v2409/void/cancel/*' => FakePluginHttp::response([
                'VoidShipmentResponse' => ['SummaryResult' => ['Status' => ['Code' => '1']]],
            ]),
        ]);

        $ok = app(UpsPlugin::class)->cancelShipment($this->connection(), '1Z999AA10123456784');

        $this->assertTrue($ok);
    }

    public function test_track_maps_status_and_events(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://wwwcie.ups.com/api/track/v1/details/*' => FakePluginHttp::response([
                'trackResponse' => ['shipment' => [[
                    'package' => [[
                        'activity' => [
                            [
                                'status' => ['type' => 'D', 'description' => 'Delivered'],
                                'date' => '20260710',
                                'time' => '093000',
                                'location' => ['address' => ['city' => 'München']],
                            ],
                            [
                                'status' => ['type' => 'I', 'description' => 'Departed from facility'],
                                'date' => '20260709',
                                'time' => '181500',
                                'location' => ['address' => ['city' => 'Nürnberg']],
                            ],
                        ],
                    ]],
                ]]],
            ]),
        ]);

        $result = app(UpsPlugin::class)->track($this->connection(), '1Z999AA10123456784');

        $this->assertSame(ShipmentStatus::Delivered, $result->status);
        $this->assertCount(2, $result->events);
        $this->assertSame('Delivered', $result->events[0]->description);
        $this->assertSame('München', $result->events[0]->location);
        $this->assertSame('2026-07-10 09:30:00', $result->events[0]->occurredAt->format('Y-m-d H:i:s'));
    }

    public function test_track_maps_exception_to_problem(): void {
        FakePluginHttp::fake([
            self::TOKEN_URL => self::tokenResponse(),
            'https://wwwcie.ups.com/api/track/v1/details/*' => FakePluginHttp::response([
                'trackResponse' => ['shipment' => [[
                    'package' => [[
                        'activity' => [[
                            'status' => ['type' => 'X', 'description' => 'Address unknown'],
                            'date' => '20260710',
                            'time' => '093000',
                        ]],
                    ]],
                ]]],
            ]),
        ]);

        $result = app(UpsPlugin::class)->track($this->connection(), '1Z999AA10123456784');

        $this->assertSame(ShipmentStatus::Problem, $result->status);
    }
}
