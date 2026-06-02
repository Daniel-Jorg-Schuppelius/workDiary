<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TravelChargeServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit;

use App\Enums\Tour\TourStatus;
use App\Enums\Travel\TravelLogVehicle;
use App\Models\{Customer, DiaryEntry, Tour, TravelLog, User};
use App\Services\Travel\TravelChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TravelChargeServiceTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $user;

    private Customer $customer;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->user = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'ACME',
            'currency' => 'EUR',
            'address_lat' => '52.5200000',
            'address_lng' => '13.4050000',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_resolve_config_merges_customer_override_over_global(): void {
        config()->set('travel.mode', 'flat');
        config()->set('travel.flat_amount', 10);
        config()->set('travel.rate_per_km', 0.30);

        $this->customer->update(['travel_settings' => ['mode' => 'km', 'rate_per_km' => 0.5]]);

        $config = app(TravelChargeService::class)->resolveConfig($this->customer);

        $this->assertSame('km', $config['mode']);          // Kunde gewinnt
        $this->assertSame(0.5, $config['rate_per_km']);    // Kunde gewinnt
        $this->assertSame(10.0, $config['flat_amount']);   // global geerbt
    }

    public function test_flat_charge_for_tour_to_customer(): void {
        config()->set('travel.enabled', true);
        config()->set('travel.mode', 'flat');
        config()->set('travel.flat_amount', 25);

        $this->tourToCustomer('2030-04-01');

        $charges = $this->charges();

        $this->assertCount(1, $charges);
        $this->assertSame(1.0, $charges->first()->quantity);
        $this->assertSame(25.0, $charges->first()->amount());
    }

    public function test_km_company_round_trip_doubles_distance(): void {
        config()->set('travel.enabled', true);
        config()->set('travel.mode', 'km');
        config()->set('travel.rate_per_km', 0.5);
        config()->set('travel.km_source', 'company');
        config()->set('travel.origin_lat', 53.5500000);
        config()->set('travel.origin_lng', 10.0000000);

        $this->tourToCustomer('2030-04-02');

        config()->set('travel.round_trip', false);
        $single = $this->charges()->first();

        // Tour wieder freigeben und mit Round-Trip neu rechnen.
        Tour::query()->update(['travel_billed' => false]);
        config()->set('travel.round_trip', true);
        $round = $this->charges()->first();

        $this->assertNotNull($single);
        $this->assertNotNull($round);
        $this->assertEqualsWithDelta($single->quantity * 2, $round->quantity, 0.01);
        $this->assertEqualsWithDelta($round->quantity * 0.5, $round->amount(), 0.01);
    }

    public function test_km_tour_uses_travel_log_distance(): void {
        config()->set('travel.enabled', true);
        config()->set('travel.mode', 'km');
        config()->set('travel.rate_per_km', 0.4);
        config()->set('travel.km_source', 'tour');

        $this->tourToCustomer('2030-04-03');

        TravelLog::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'date' => '2030-04-03',
            'distance_km' => '42.00',
            'vehicle' => TravelLogVehicle::cases()[0]->value,
        ]);

        $charge = $this->charges()->first();

        $this->assertNotNull($charge);
        $this->assertEqualsWithDelta(42.0, $charge->quantity, 0.01);
        $this->assertEqualsWithDelta(16.8, $charge->amount(), 0.01); // 42 * 0,40
    }

    public function test_disabled_yields_no_charges(): void {
        config()->set('travel.enabled', false);
        config()->set('travel.mode', 'flat');
        config()->set('travel.flat_amount', 25);
        $this->tourToCustomer('2030-04-04');

        $this->assertCount(0, $this->charges());
    }

    private function tourToCustomer(string $date): Tour {
        $tour = Tour::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'tour_date' => $date,
            'status' => TourStatus::Completed->value,
            'planned_distance_km' => '0',
            'planned_duration_minutes' => 0,
            'start_lat' => '52.0000000',
            'start_lng' => '13.0000000',
        ]);

        DiaryEntry::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->user->id,
            'customer_id' => $this->customer->id,
            'tour_id' => $tour->id,
            'tour_position' => 1,
        ]);

        return $tour;
    }

    /** @return \Illuminate\Support\Collection<int, \App\Services\Travel\TravelCharge> */
    private function charges() {
        return app(TravelChargeService::class)->chargesForRange(
            $this->customer,
            null,
            ['from' => '2030-01-01', 'to' => '2030-12-31'],
            null,
            false,
        );
    }
}
