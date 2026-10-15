<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use App\Enums\Diary\{Mode, Status as DiaryStatus};
use App\Enums\Tour\TourStatus;
use App\Enums\Travel\TravelLogVehicle;
use App\Enums\User\Permission;
use App\Exceptions\TourAlreadyMaterializedException;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, Tour};
use App\Models\Fleet\Vehicle;
use App\Models\Platform\User;
use App\Models\Travel\TravelLog;
use App\Services\Routing\TourService;
use App\Support\{Sqid, Tz};
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\Support\FakePluginHttp;
use Tests\TestCase;

class TourTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    public function test_create_draft_persists_with_home_anchors(): void {
        $driver = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'home_address' => 'Hauptstr. 1, Berlin',
            'home_lat' => 52.5,
            'home_lng' => 13.4,
        ]);

        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $this->assertSame('2026-06-01', $tour->tour_date?->toDateString());
        $this->assertSame('Hauptstr. 1, Berlin', $tour->start_address);
        $this->assertSame('52.5000000', (string) $tour->start_lat);
        $this->assertSame(TourStatus::Draft, $tour->status);
    }

    public function test_assign_orders_sets_positions_and_status(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $orders = DiaryEntry::factory()->service()->count(3)->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
        ]);
        $ids = $orders->pluck('id')->all();
        $service->assignOrders($tour, $ids);

        $orders->each(fn(DiaryEntry $o) => $o->refresh());
        $this->assertSame(1, $orders[0]->tour_position);
        $this->assertSame(2, $orders[1]->tour_position);
        $this->assertSame(3, $orders[2]->tour_position);
        $this->assertSame(DiaryStatus::InProgress, $orders[0]->status);
        $this->assertSame((int) $driver->id, (int) $orders[0]->assigned_user_id);
    }

    public function test_recalculate_optimizes_order_with_haversine_fallback(): void {
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');

        $driver = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'home_lat' => 52.5,
            'home_lng' => 13.0,
            'home_address' => 'Start',
        ]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        // Three stops with reverse-of-optimal initial order.
        $reverse = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.4,
        ]);
        $middle = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.2,
        ]);
        $near = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.1,
        ]);
        $service->assignOrders($tour, [$reverse->id, $middle->id, $near->id]);

        $result = $service->recalculate($tour->fresh());

        $this->assertSame([$near->id, $middle->id, $reverse->id], $result['order']);
        $this->assertGreaterThan(0.0, $result['distance_km']);
    }

    public function test_state_machine_blocks_invalid_transitions(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $service->complete($service->start($tour));

        $this->expectException(\RuntimeException::class);
        $service->start($tour->fresh());
    }

    public function test_materialize_creates_travel_log_per_leg(): void {
        $driver = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'home_lat' => 52.5,
            'home_lng' => 13.0,
            'home_address' => 'Home',
        ]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $a = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.1,
        ]);
        $b = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.2,
        ]);
        $service->assignOrders($tour, [$a->id, $b->id]);

        $logs = $service->materializeToTravelLogs($tour->fresh());

        // home → A, A → B, B → home (end equals start because home set both)
        $this->assertCount(3, $logs);
        $this->assertSame((int) $driver->id, (int) $logs[0]->user_id);
        $this->assertGreaterThan(0.0, (float) $logs[0]->distance_km);
        $this->assertSame(TravelLogVehicle::Private_, $logs[0]->vehicle);
    }

    public function test_index_requires_auth(): void {
        $this->get(route('tours.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_list_other_users_tours(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Tour::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'name' => 'Wochentour',
        ]);

        $this->actingAs($admin)
            ->get(route('tours.index', ['user' => Sqid::encode(User::class, $worker->id), 'from' => CarbonImmutable::today()->toDateString(), 'to' => CarbonImmutable::today()->toDateString()]))
            ->assertOk()
            ->assertSee('Wochentour');
    }

    public function test_admin_can_list_other_users_tours_with_numeric_user_fallback(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Tour::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'name' => 'Numerische Wochentour',
        ]);

        $this->actingAs($admin)
            ->get(route('tours.index', [
                'user' => (string) $worker->id,
                'from' => CarbonImmutable::today()->toDateString(),
                'to' => CarbonImmutable::today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Numerische Wochentour');
    }

    public function test_assigning_flex_order_promotes_it_to_fixed_with_tour_date(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $order = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'mode' => Mode::Backlog,
            'start_at' => null,
            'end_at' => null,
            'scheduled_for' => null,
            'time_window_start' => null,
            'time_window_end' => null,
            'service_minutes' => 45,
        ]);

        $service->assignOrders($tour, [$order->id]);

        $order->refresh();
        $this->assertSame(Mode::Fixed, $order->mode);
        $this->assertSame('2026-06-01', $order->scheduled_for?->toDateString());
        $this->assertSame('2026-06-01 08:00:00', $order->start_at?->setTimezone(Tz::ofOrganization($this->organization))->toDateTimeString());
        $this->assertSame('2026-06-01 08:45:00', $order->end_at?->setTimezone(Tz::ofOrganization($this->organization))->toDateTimeString());
    }

    public function test_assigning_flex_order_respects_existing_time_window(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-02'));

        $order = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'mode' => Mode::Deadline,
            'due_date' => '2026-06-30',
            'start_at' => null,
            'end_at' => null,
            'time_window_start' => '14:30',
            'time_window_end' => '15:30',
            'service_minutes' => null,
        ]);

        $service->assignOrders($tour, [$order->id]);

        $order->refresh();
        $this->assertSame(Mode::Fixed, $order->mode);
        // Zeitfenster sind Ortszeit, gespeichert wird UTC.
        $this->assertSame('2026-06-02 14:30:00', $order->start_at?->setTimezone(Tz::ofOrganization($this->organization))->toDateTimeString());
        $this->assertSame('2026-06-02 12:30:00', $order->getRawOriginal('start_at'));
        $this->assertSame('2026-06-02 15:30:00', $order->end_at?->setTimezone(Tz::ofOrganization($this->organization))->toDateTimeString());
    }

    public function test_recalculate_uses_osrm_table_and_persists_geometry(): void {
        $fake = FakePluginHttp::fake([
            '*/table/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'distances' => [
                    [0, 1000, 2000, 500, 500],
                    [1000, 0, 1000, 1500, 1500],
                    [2000, 1000, 0, 2500, 2500],
                    [500, 1500, 2500, 0, 0],
                    [500, 1500, 2500, 0, 0],
                ],
                'durations' => [
                    [0, 100, 200, 50, 50],
                    [100, 0, 100, 150, 150],
                    [200, 100, 0, 250, 250],
                    [50, 150, 250, 0, 0],
                    [50, 150, 250, 0, 0],
                ],
            ]),
            '*/route/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 4200.0,
                    'duration' => 600.0,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[13.0, 52.5], [13.4, 52.5]]],
                    'legs' => [],
                ]],
            ]),
        ]);

        $driver = User::factory()->user()->create([
            'organization_id' => $this->organization->id,
            'home_lat' => 52.5,
            'home_lng' => 13.0,
            'home_address' => 'Start',
        ]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $a = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.1,
        ]);
        $b = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.2,
        ]);
        $c = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => '2026-06-01',
            'address_lat' => 52.5,
            'address_lng' => 13.3,
        ]);
        $service->assignOrders($tour, [$a->id, $b->id, $c->id]);

        $result = $service->recalculate($tour->fresh());

        $fake->assertSent(fn($request) => str_contains((string) $request->getUri(), '/table/v1/'));
        $this->assertNotNull($tour->fresh()->route_geometry);
        $this->assertCount(3, $result['order']);
    }

    public function test_map_page_renders_for_admin(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        Tour::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $admin->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'name' => 'Kartentour',
        ]);

        $this->actingAs($admin)
            ->get(route('tours.map', [
                'from' => CarbonImmutable::today()->toDateString(),
                'to' => CarbonImmutable::today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('data-map', false);
    }

    /** Sicherheitsaudit 2026-09-17 (authz-tour-1): fremde Aufträge lassen sich nicht in die eigene Tour ziehen. */
    public function test_employee_cannot_pull_foreign_orders_or_reset_finished_ones(): void {
        $employee = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $colleague = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $tour = Tour::factory()->create(['organization_id' => $this->organization->id, 'user_id' => $employee->id, 'tour_date' => CarbonImmutable::today()->toDateString()]);
        $foreign = DiaryEntry::factory()->for($colleague)->service()->create(['organization_id' => $this->organization->id, 'status' => DiaryStatus::Open]);
        $own = DiaryEntry::factory()->for($employee)->service()->create(['organization_id' => $this->organization->id, 'status' => DiaryStatus::Open]);

        $this->actingAs($employee)->put(route('tours.update', $tour), [
            'user_id' => $employee->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'order_ids' => [$foreign->sqid, $own->sqid],
        ])->assertRedirect();

        $this->assertNull($foreign->fresh()?->tour_id);
        $this->assertSame(DiaryStatus::Open, $foreign->fresh()?->status);
        $this->assertSame($tour->id, (int) $own->fresh()?->tour_id);

        // Abgerechnete Aufträge behalten beim Lösen ihren Status.
        $own->forceFill(['status' => DiaryStatus::Invoiced])->saveQuietly();
        app(TourService::class)->assignOrders($tour, []);
        $this->assertSame(DiaryStatus::Invoiced, $own->fresh()?->status);
        $this->assertNull($own->fresh()?->tour_id);

        // Tour einem Kollegen unterschieben geht nur als Admin.
        $this->actingAs($employee)->put(route('tours.update', $tour), [
            'user_id' => $colleague->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
        ])->assertForbidden();
    }

    /** Sicherheitsaudit 2026-09-17 (S-18-Rest): Leaflet setzt Popups per innerHTML. */
    public function test_map_popup_escapes_tour_and_driver_names(): void {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $driver = User::factory()->create(['organization_id' => $this->organization->id, 'name' => '<b x-init="$el.click()">Fahrer</b>']);
        $tour = Tour::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $driver->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'name' => '<img src=x onerror=alert(1)>',
        ]);
        $stop = DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id,
            'scheduled_for' => CarbonImmutable::today()->toDateString(),
            'address_lat' => 52.5,
            'address_lng' => 13.4,
        ]);
        app(TourService::class)->assignOrders($tour, [$stop->id]);

        $html = $this->actingAs($admin)
            ->get(route('tours.map', ['from' => CarbonImmutable::today()->toDateString(), 'to' => CarbonImmutable::today()->toDateString(), 'user' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-map\s+x-ignore/', (string) $html);
        preg_match('/data-config="([^"]+)"/', (string) $html, $match);
        $config = json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES), true);
        $popups = array_values(array_filter(array_column($config['markers'] ?? [], 'popup')));
        $this->assertNotEmpty($popups);
        $this->assertStringNotContainsString('<img', $popups[0]);
        $this->assertStringNotContainsString('<b x-init', $popups[0]);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $popups[0]);
    }

    public function test_routing_setting_override_resolves_from_organization(): void {
        $this->organization->forceFill([
            'settings' => ['routing' => ['osrm' => ['base_url' => 'http://osrm.internal:5000']]],
        ])->save();
        app()->instance('currentOrganization', $this->organization->fresh());

        $this->assertSame('http://osrm.internal:5000', \App\Support\Setting::get('routing.osrm.base_url'));
        // Falls keine Org-Override existiert, greift der config()-Default.
        $this->assertSame(config('routing.osrm.profile'), \App\Support\Setting::get('routing.osrm.profile'));
    }

    /** MVP-1170: Optimieren behält Stopps ohne Koordinaten — hinten, in der Tour, mit Status. */
    public function test_recalculate_keeps_stops_without_coordinates_in_the_tour(): void {
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Start']);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $far = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.4]);
        $unlocated = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => null, 'address_lng' => null]);
        $near = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.1]);
        $service->assignOrders($tour, [$far->id, $unlocated->id, $near->id]);

        $result = $service->recalculate($tour->fresh());

        $this->assertSame([$near->id, $far->id, $unlocated->id], $result['order']);
        $unlocated->refresh();
        $this->assertSame((int) $tour->id, (int) $unlocated->tour_id);
        $this->assertSame(3, $unlocated->tour_position);
        $this->assertSame(DiaryStatus::InProgress, $unlocated->status);
    }

    /** MVP-1170: Ohne eigene Koordinaten gilt die Kundenadresse. */
    public function test_recalculate_falls_back_to_customer_coordinates(): void {
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Start']);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));

        $farCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.5, 'address_lng' => 13.4]);
        $nearCustomer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.5, 'address_lng' => 13.1]);
        $far = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'customer_id' => $farCustomer->id, 'address_lat' => null, 'address_lng' => null]);
        $near = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'customer_id' => $nearCustomer->id, 'address_lat' => null, 'address_lng' => null]);
        $service->assignOrders($tour, [$far->id, $near->id]);

        $result = $service->recalculate($tour->fresh());

        $this->assertSame([$near->id, $far->id], $result['order']);
        $this->assertGreaterThan(0.0, $result['distance_km']);
    }

    /** MVP-1172: Die OSRM-Route liefert Distanz und Dauer je Bein; die Tour merkt sie sich. */
    public function test_recalculate_stores_route_legs(): void {
        FakePluginHttp::fake([
            '*/table/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'distances' => [[0, 500, 500], [500, 0, 0], [500, 0, 0]],
                'durations' => [[0, 60, 60], [60, 0, 0], [60, 0, 0]],
            ]),
            '*/route/*' => FakePluginHttp::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 1300.0,
                    'duration' => 140.0,
                    'geometry' => ['type' => 'LineString', 'coordinates' => [[13.0, 52.5], [13.1, 52.5]]],
                    'legs' => [['distance' => 650.4, 'duration' => 70.0], ['distance' => 649.6, 'duration' => 70.0]],
                ]],
            ]),
        ]);
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Start']);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $stop = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.1]);
        $service->assignOrders($tour, [$stop->id]);

        $service->recalculate($tour->fresh());

        $legs = $tour->fresh()->route_legs;
        $this->assertSame([
            ['from' => 'start', 'to' => (string) $stop->id, 'distance_m' => 650, 'duration_s' => 70],
            ['from' => (string) $stop->id, 'to' => 'end', 'distance_m' => 650, 'duration_s' => 70],
        ], $legs);
    }

    /** MVP-1172: Fahrten nehmen Straßenkilometer aus den Abschnitten, kennen ihre Tour und entstehen nur einmal. */
    public function test_materialize_uses_route_legs_and_runs_once(): void {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Home']);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $stop = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.1, 'start_at' => '2026-06-01 08:00:00', 'end_at' => '2026-06-01 09:00:00']);
        $service->assignOrders($tour, [$stop->id]);
        $tour->forceFill(['route_legs' => [
            ['from' => 'start', 'to' => (string) $stop->id, 'distance_m' => 12345, 'duration_s' => 900],
            ['from' => (string) $stop->id, 'to' => 'end', 'distance_m' => 5000, 'duration_s' => 400],
        ]])->save();

        $logs = $service->materializeToTravelLogs($tour->fresh());

        $this->assertCount(2, $logs);
        $this->assertSame('12.35', (string) $logs[0]->distance_km);
        $this->assertSame((int) $tour->id, (int) $logs[0]->tour_id);
        $this->assertSame(15, $logs[0]->duration_minutes);
        $this->assertSame('2026-06-01 07:45:00', $logs[0]->started_at?->toDateTimeString());
        $this->assertSame('2026-06-01 08:00:00', $logs[0]->ended_at?->toDateTimeString());
        $this->assertSame('2026-06-01 09:00:00', $logs[1]->started_at?->toDateTimeString());
        $this->assertStringNotContainsString('Luftlinie', (string) $logs[0]->purpose);

        $this->expectException(TourAlreadyMaterializedException::class);
        $service->materializeToTravelLogs($tour->fresh());
    }

    /** MVP-1172: Übernahme erst ab gestarteter Tour; Fahrtenbuch-Fahrzeug braucht den Tachostand und bekommt eine lückenlose Kette. */
    public function test_materialize_guards_status_and_builds_odometer_chain_for_logbook_vehicles(): void {
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Home']);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $a = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.1]);
        $b = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.2]);
        $service->assignOrders($tour, [$a->id, $b->id]);

        // Entwurf: keine Übernahme.
        $this->actingAs($driver)->post(route('tours.materialize', $tour))->assertSessionHasErrors('tour');
        $this->assertSame(0, TravelLog::query()->count());

        $vehicle = Vehicle::factory()->create(['organization_id' => $this->organization->id, 'logbook_mode' => true]);
        $tour->forceFill(['vehicle_id' => $vehicle->id])->save();
        $service->start($tour->fresh());

        // Fahrtenbuch-Modus ohne Tachostand: Formularfehler statt Serverfehler.
        $this->actingAs($driver)->post(route('tours.materialize', $tour))->assertSessionHasErrors('odometer_start_km');
        $this->assertSame(0, TravelLog::query()->count());

        $this->actingAs($driver)->post(route('tours.materialize', $tour), ['odometer_start_km' => 10000])
            ->assertRedirect(route('tours.show', $tour));
        $logs = TravelLog::query()->orderBy('id')->get();
        $this->assertCount(3, $logs);
        $this->assertSame(10000, $logs[0]->odometer_start_km);
        $this->assertSame($logs[0]->odometer_end_km, $logs[1]->odometer_start_km);
        $this->assertSame($logs[1]->odometer_end_km, $logs[2]->odometer_start_km);
        foreach ($logs as $log) {
            $this->assertSame((float) ($log->odometer_end_km - $log->odometer_start_km), (float) $log->distance_km);
            $this->assertStringContainsString('Luftlinie', (string) $log->purpose);
        }

        // Zweiter Klick: Fehler, nichts doppelt.
        $this->actingAs($driver)->post(route('tours.materialize', $tour), ['odometer_start_km' => 10000])->assertSessionHasErrors('tour');
        $this->assertSame(3, TravelLog::query()->count());
    }

    /** MVP-1173: Heimadressen der Kollegen sieht nur die Disposition; das Tourrecht ist Pflicht. */
    public function test_map_shows_colleague_homes_only_to_dispatchers_and_requires_the_tour_right(): void {
        User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Kollegin', 'home_lat' => 51.1234567, 'home_lng' => 9.7654321, 'home_address' => 'Privatweg 7']);
        $viewer = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 50.1111111, 'home_lng' => 8.2222222]);

        $html = (string) $this->actingAs($viewer)->get(route('tours.map'))->assertOk()->getContent();
        $this->assertStringNotContainsString('51.1234567', $html);
        $this->assertStringContainsString('50.1111111', $html);

        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($admin)->get(route('tours.map'))->assertOk()->assertSee('51.1234567', false);

        $noRole = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->assertFalse($noRole->can(Permission::TourViewAny->value));
        $this->actingAs($noRole)->get(route('tours.index'))->assertForbidden();
    }
}
