<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GeocodeCustomersCommandTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class GeocodeCustomersCommandTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        config()->set('routing.nominatim.base_url', 'http://nominatim.test');
        config()->set('routing.nominatim.rate_limit_per_sec', 1000);
    }

    public function test_backfills_customer_coordinates(): void {
        Http::fake([
            'nominatim.test/*' => Http::response([
                ['lat' => '52.5', 'lon' => '13.4', 'display_name' => 'Berlin'],
            ], 200),
        ]);

        $customer = Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_street' => 'Unter den Linden 1',
            'address_zip' => '10117',
            'address_city' => 'Berlin',
            'address_lat' => null,
            'address_lng' => null,
        ]);

        $this->artisan('geocode:customers')->assertSuccessful();

        $customer->refresh();
        $this->assertSame('52.5000000', (string) $customer->address_lat);
        $this->assertSame('13.4000000', (string) $customer->address_lng);
    }

    public function test_skips_customers_with_existing_coordinates(): void {
        Http::fake();

        Customer::factory()->create([
            'organization_id' => $this->organization->id,
            'address_street' => 'Foo 1',
            'address_lat' => 1.0,
            'address_lng' => 2.0,
        ]);

        $this->artisan('geocode:customers')->assertSuccessful();
        Http::assertNothingSent();
    }
}
