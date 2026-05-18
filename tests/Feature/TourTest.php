<?php

/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TourTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature;

use App\Models\DiaryEntry;
use App\Models\Tour;
use App\Models\TravelLog;
use App\Models\User;
use App\Services\Routing\TourService;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

class TourTest extends TestCase
{
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        config()->set('timesheet.travel.auto_create_time_entry', false);
    }

    public function test_create_draft_persists_with_home_anchors(): void
    {
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
        $this->assertSame('draft', $tour->status);
    }

    public function test_assign_orders_sets_positions_and_status(): void
    {
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

        $orders->each(fn ($o) => $o->refresh());
        $this->assertSame(1, $orders[0]->tour_position);
        $this->assertSame(2, $orders[1]->tour_position);
        $this->assertSame(3, $orders[2]->tour_position);
        $this->assertSame(DiaryEntry::STATUS_IN_PROGRESS, $orders[0]->status);
        $this->assertSame((int) $driver->id, (int) $orders[0]->assigned_user_id);
    }

    public function test_recalculate_optimizes_order_with_haversine_fallback(): void
    {
        config()->set('routing.osrm.base_url', 'http://does-not-exist.invalid');

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

    public function test_state_machine_blocks_invalid_transitions(): void
    {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        /** @var TourService $service */
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $service->complete($service->start($tour));

        $this->expectException(\RuntimeException::class);
        $service->start($tour->fresh());
    }

    public function test_materialize_creates_travel_log_per_leg(): void
    {
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
        $this->assertSame(TravelLog::VEHICLE_PRIVATE, $logs[0]->vehicle);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('tours.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_list_other_users_tours(): void
    {
        $admin = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        Tour::factory()->create([
            'organization_id' => $this->organization->id,
            'user_id' => $worker->id,
            'tour_date' => CarbonImmutable::today()->toDateString(),
            'name' => 'Wochentour',
        ]);

        $this->actingAs($admin)
            ->get(route('tours.index', ['user' => $worker->id, 'from' => CarbonImmutable::today()->toDateString(), 'to' => CarbonImmutable::today()->toDateString()]))
            ->assertOk()
            ->assertSee('Wochentour');
    }
}
