<?php
/*
 * Created on   : Mon Jul 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Enums\Notification\NotificationEvent;
use App\Enums\Shipping\ShipmentStatus;
use App\Models\{CarrierConnection, ExternalReference, Shipment, User};
use App\Notifications\GenericEventNotification;
use App\Services\Shipping\{ShipmentPackage, ShipmentRecipient, ShipmentRequest, ShipmentService, ShippingProviderRegistry, TrackingEvent, TrackingResult};
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Notification, Storage};
use Tests\Concerns\WithOrganization;
use Tests\Support\FakeShippingProvider;
use Tests\TestCase;

class ShipmentServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private FakeShippingProvider $provider;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->setUpOrganization();
        Storage::fake('local');

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin);

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

    private function service(): ShipmentService {
        return app(ShipmentService::class);
    }

    private function makeShipment(): Shipment {
        return Shipment::query()->create([
            'organization_id' => $this->organization->id,
            'carrier' => 'mock',
            'status' => ShipmentStatus::Draft->value,
        ]);
    }

    private function makeRequest(): ShipmentRequest {
        return new ShipmentRequest(
            new ShipmentRecipient('Max Mustermann', 'Teststr. 1', '10115', 'Berlin', 'DE', null, 'max@example.test'),
            [new ShipmentPackage(1000)],
            'REF-1',
        );
    }

    public function test_create_label_stores_attachment_status_and_reference(): void {
        $shipment = $this->makeShipment();

        $this->service()->createLabel($shipment, $this->makeRequest());

        $fresh = $shipment->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(ShipmentStatus::Labeled, $fresh->status);
        $this->assertSame('TRACK-1', $fresh->tracking_number);
        $this->assertSame('CARRIER-1', $fresh->carrier_shipment_id);
        $this->assertSame('Berlin', $fresh->recipient_snapshot['city'] ?? null);

        $attachment = $fresh->attachmentByMeta(Shipment::LABEL_META);
        $this->assertNotNull($attachment);
        $this->assertSame('application/pdf', $attachment->mime);
        $this->assertTrue(Storage::disk('local')->exists($attachment->path));

        $this->assertDatabaseHas('external_references', [
            'plugin_id' => 'mock',
            'external_type' => 'shipment',
            'referenceable_type' => $shipment->getMorphClass(),
            'referenceable_id' => $shipment->getKey(),
            'external_id' => 'CARRIER-1',
        ]);
    }

    public function test_create_label_is_idempotent(): void {
        $shipment = $this->makeShipment();
        $request = $this->makeRequest();

        $this->service()->createLabel($shipment, $request);
        $this->service()->createLabel($shipment->fresh() ?? $shipment, $request);

        $this->assertSame(1, $this->provider->createCount);
        $this->assertSame('TRACK-1', $shipment->fresh()?->tracking_number);
        $this->assertSame(1, ExternalReference::query()->where('external_type', 'shipment')->count());
    }

    public function test_cancel_marks_cancelled_and_calls_provider(): void {
        $shipment = $this->makeShipment();
        $this->service()->createLabel($shipment, $this->makeRequest());

        $ok = $this->service()->cancel($shipment->fresh() ?? $shipment);

        $this->assertTrue($ok);
        $this->assertSame(1, $this->provider->cancelCount);
        $this->assertSame(ShipmentStatus::Cancelled, $shipment->fresh()?->status);
    }

    public function test_refresh_tracking_applies_events_and_status(): void {
        $shipment = $this->makeShipment();
        $this->service()->createLabel($shipment, $this->makeRequest());

        $this->provider->setTrackingResult(new TrackingResult(
            ShipmentStatus::InTransit,
            [new TrackingEvent(Carbon::parse('2026-07-05 08:00:00'), 'Im Paketzentrum', 'Berlin')],
        ));

        $this->service()->refreshTracking($shipment->fresh() ?? $shipment);

        $fresh = $shipment->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame(ShipmentStatus::InTransit, $fresh->status);
        $this->assertCount(1, $fresh->events ?? []);
        $this->assertSame('Im Paketzentrum', $fresh->events[0]['description'] ?? null);
        $this->assertNotNull($fresh->last_tracked_at);
    }

    public function test_delivery_problem_notifies_team_lead_once(): void {
        $teamlead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $shipment = $this->makeShipment();
        $this->service()->createLabel($shipment, $this->makeRequest());

        $this->provider->setTrackingResult(new TrackingResult(ShipmentStatus::Problem, []));

        Notification::fake();
        $this->service()->refreshTracking($shipment->fresh() ?? $shipment);

        Notification::assertSentTo(
            $teamlead,
            GenericEventNotification::class,
            fn(GenericEventNotification $n): bool => $n->event === NotificationEvent::ShipmentDeliveryProblem,
        );

        // Erneutes Tracking mit weiterhin „problem" feuert die Meldung nicht erneut.
        Notification::fake();
        $this->service()->refreshTracking($shipment->fresh() ?? $shipment);
        Notification::assertNothingSent();
    }
}
