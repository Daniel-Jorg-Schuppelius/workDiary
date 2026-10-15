<?php

namespace Tests\Feature\Location;

use App\Enums\Location\LocationVisitStatus;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, TourStop};
use App\Models\Location\{CustomerGeofence, LocationPendingEntry, LocationVisit};
use App\Models\Platform\User;
use App\Models\Time\TimeEntry;
use App\Services\Location\{VisitMaterializer, ZoneFactory};
use App\Services\Routing\TourService;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1181/1182: Zonen aus Adressen und Tourstopps, Besuch → Auftrag, Bestätigung → Stopp-Ist. */
class TourZoneLinkTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    public function test_zone_factory_creates_customer_zone_once(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $factory = app(ZoneFactory::class);

        $zone = $factory->forCustomer($customer, $admin);
        $this->assertNotNull($zone);
        $this->assertSame(100, $zone->radius_m);
        $this->assertNull($factory->forCustomer($customer, $admin), 'Keine zweite Zone am selben Ort.');
        $this->assertSame(0, $factory->forAllCustomers($admin));

        $this->actingAs($admin)->post(route('geofences.from-customers'))->assertRedirect(route('geofences.index'));
    }

    public function test_tour_start_creates_temporary_zones_and_visit_links_the_order(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0]);
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $stop = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.52, 'address_lng' => 13.405, 'title' => 'Heizung warten']);
        $service->assignOrders($tour, [$stop->id]);

        $service->start($tour->fresh());

        $zone = CustomerGeofence::query()->where('tour_id', $tour->id)->firstOrFail();
        $this->assertSame((int) $stop->id, (int) $zone->diary_entry_id);
        $this->assertSame('2026-06-01', $zone->valid_on?->toDateString());
        $this->assertTrue($zone->isTemporary());
        $this->assertTrue($zone->coversDate(CarbonImmutable::parse('2026-06-01 09:00', 'Europe/Berlin')));
        $this->assertFalse($zone->coversDate(CarbonImmutable::parse('2026-06-02 09:00', 'Europe/Berlin')));
        // Die Pflegeliste zeigt Tourzonen nicht.
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin)->get(route('geofences.index'))->assertOk()->assertDontSee('Heizung warten');

        LocationVisit::create([
            'organization_id' => $this->organization->id, 'user_id' => $driver->id, 'customer_geofence_id' => $zone->id,
            'entered_at' => '2026-06-01 07:00:00', 'left_at' => '2026-06-01 08:30:00', 'duration_min' => 90, 'sample_count' => 5,
            'status' => LocationVisitStatus::Closed, 'materialized' => false,
        ]);
        app(VisitMaterializer::class)->materializeForUser($driver);

        $pending = LocationPendingEntry::query()->firstOrFail();
        $this->assertSame((int) $stop->id, (int) $pending->diary_entry_id);
        $this->assertSame('Heizung warten', $pending->description);

        $this->actingAs($driver)->post(route('location.review.confirm', $pending))->assertRedirect();

        $this->assertSame((int) $stop->id, (int) TimeEntry::query()->firstOrFail()->diary_entry_id);
        $tourStop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $stop->id)->firstOrFail();
        $this->assertSame('2026-06-01 07:00:00', $tourStop->arrived_at?->toDateTimeString());
        $this->assertSame('2026-06-01 08:30:00', $tourStop->departed_at?->toDateTimeString());
        $this->assertSame(TourStop::SOURCE_GEOFENCE, $tourStop->actual_source);
    }

    public function test_customer_zone_visit_matches_the_tour_stop_of_the_day(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.52, 'address_lng' => 13.405]);
        $zone = app(ZoneFactory::class)->forCustomer($customer);
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $stop = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'customer_id' => $customer->id, 'address_lat' => null, 'address_lng' => null]);
        $service->assignOrders($tour, [$stop->id]);

        LocationVisit::create([
            'organization_id' => $this->organization->id, 'user_id' => $driver->id, 'customer_geofence_id' => $zone->id,
            'entered_at' => '2026-06-01 10:00:00', 'left_at' => '2026-06-01 10:45:00', 'duration_min' => 45, 'sample_count' => 4,
            'status' => LocationVisitStatus::Closed, 'materialized' => false,
        ]);
        app(VisitMaterializer::class)->materializeForUser($driver);

        $this->assertSame((int) $stop->id, (int) LocationPendingEntry::query()->firstOrFail()->diary_entry_id);
    }
}
