<?php
/*
 * Created on   : Sat Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ShipmentAdminCarrierTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Shipping;

use App\Models\{CarrierConnection, User};
use App\Services\Shipping\ShippingProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Carrier-Auswahl + carrier-spezifische Pflicht-Zugangsdaten im Versand-Admin
 * (Feature 059, MVP-128 / Bauturbo A5): DHL/UPS/FedEx sind registriert und
 * wählbar; OAuth2-Carrier (UPS/FedEx) brauchen bei Neuanlage keinen api_key,
 * DHL weiterhin schon.
 */
class ShipmentAdminCarrierTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $admin;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();

        $this->admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($this->admin);
    }

    public function test_registry_offers_all_three_carriers(): void {
        $carriers = app(ShippingProviderRegistry::class)->carriers();

        $this->assertContains('dhl', $carriers);
        $this->assertContains('ups', $carriers);
        $this->assertContains('fedex', $carriers);
    }

    public function test_oauth_carrier_connection_saves_without_api_key(): void {
        $this->post(route('admin.shipments.connections.store'), [
            'carrier' => 'ups',
            'name' => 'UPS Produktiv',
            'username' => 'client-id',
            'password' => 'client-secret',
            'billing_number' => 'A1B2C3',
            'active' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $connection = CarrierConnection::query()->where('carrier', 'ups')->first();
        $this->assertNotNull($connection);
        $this->assertSame('client-id', $connection->credential('username'));
        $this->assertNull($connection->credential('api_key'));
    }

    public function test_dhl_connection_still_requires_api_key(): void {
        $this->post(route('admin.shipments.connections.store'), [
            'carrier' => 'dhl',
            'name' => 'DHL',
            'username' => 'gk-user',
            'password' => 'gk-pass',
            // api_key fehlt → Neuanlage abgelehnt
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, CarrierConnection::query()->where('carrier', 'dhl')->count());
    }
}
