<?php

namespace Tests\Feature\Routing;

use App\Enums\Diary\{DispatchStatus, Status as DiaryStatus};
use App\Enums\Tour\TourStatus;
use App\Models\Diary\{DiaryEntry, TourStop};
use App\Models\Platform\User;
use App\Services\Routing\{ServiceDurationSuggester, TourService};
use App\Services\Sync\SyncCommandService;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1183/1184: Fahreransicht mit Stopp-Ist, Tourabschluss ohne Sammel-Erledigung, Offline-Befehle, Erfahrungswerte und Plan-Ist-Bericht. */
class TourActualsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    /** @return array{0: User, 1: \App\Models\Diary\Tour, 2: DiaryEntry, 3: DiaryEntry} */
    private function runningTour(): array {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0]);
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $a = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.1, 'service_minutes' => 30]);
        $b = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.2, 'service_minutes' => 30]);
        $service->assignOrders($tour, [$a->id, $b->id]);
        $service->start($tour->fresh());

        return [$driver, $tour->fresh(), $a, $b];
    }

    public function test_driver_view_records_arrival_and_outcome_and_completion_keeps_open_stops(): void {
        [$driver, $tour, $a, $b] = $this->runningTour();
        $stopA = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $a->id)->firstOrFail();
        $stopB = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $b->id)->firstOrFail();

        $this->actingAs($driver)->get(route('tours.driver', $tour))->assertOk()->assertSee('Angekommen');

        $this->actingAs($driver)->post(route('tours.stops.arrive', [$tour, $stopA]))->assertRedirect(route('tours.driver', $tour));
        $stopA->refresh();
        $this->assertNotNull($stopA->arrived_at);
        $this->assertSame(TourStop::SOURCE_MANUAL, $stopA->actual_source);
        $this->assertSame(DispatchStatus::EnRoute, $a->fresh()->dispatch_status);

        $this->actingAs($driver)->post(route('tours.stops.depart', [$tour, $stopA]), ['outcome' => TourStop::OUTCOME_DONE])->assertRedirect();
        $stopA->refresh();
        $this->assertNotNull($stopA->departed_at);
        $this->assertSame(TourStop::OUTCOME_DONE, $stopA->outcome);
        $this->assertSame(DiaryStatus::Done, $a->fresh()->status);

        $this->actingAs($driver)->post(route('tours.stops.depart', [$tour, $stopB]), ['outcome' => TourStop::OUTCOME_NOT_MET])->assertRedirect();
        $this->assertSame(TourStop::OUTCOME_NOT_MET, $stopB->fresh()->outcome);
        $this->assertSame(DiaryStatus::InProgress, $b->fresh()->status, 'Nicht angetroffen lässt den Auftrag offen.');

        app(TourService::class)->complete($tour->fresh());
        $this->assertSame(TourStatus::Completed, $tour->fresh()->status);
        $this->assertSame(DiaryStatus::InProgress, $b->fresh()->status, 'Tour beenden erledigt keine offenen Stopps mehr.');

        $events = $tour->journal()->get()->pluck('event')->all();
        $this->assertContains('tour.stop_arrived', $events);
        $this->assertContains('tour.stop_departed', $events);
        $this->assertContains('tour.completed', $events);
    }

    public function test_foreign_driver_cannot_touch_stops(): void {
        [, $tour, $a] = $this->runningTour();
        $stop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $a->id)->firstOrFail();
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)->get(route('tours.driver', $tour))->assertForbidden();
        $this->actingAs($other)->post(route('tours.stops.arrive', [$tour, $stop]))->assertForbidden();
    }

    public function test_offline_sync_commands_record_stop_actuals_with_device_time(): void {
        [$driver, $tour, $a] = $this->runningTour();
        $stop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $a->id)->firstOrFail();

        $sync = app(SyncCommandService::class);
        $arrive = $sync->handle($driver, ['client_uuid' => 'a1b2c3d4-0000-4000-8000-000000000001', 'type' => 'tour.stop-arrive', 'payload' => ['stop' => $stop->sqid, 'at' => '2026-06-01T07:05:00Z']]);
        $this->assertSame('applied', $arrive['status']);
        $depart = $sync->handle($driver, ['client_uuid' => 'a1b2c3d4-0000-4000-8000-000000000002', 'type' => 'tour.stop-depart', 'payload' => ['stop' => $stop->sqid, 'outcome' => TourStop::OUTCOME_DONE, 'at' => '2026-06-01T07:40:00Z']]);
        $this->assertSame('applied', $depart['status']);

        $stop->refresh();
        $this->assertSame('2026-06-01 07:05:00', $stop->arrived_at?->toDateTimeString());
        $this->assertSame('2026-06-01 07:40:00', $stop->departed_at?->toDateTimeString());
        $this->assertSame(DiaryStatus::Done, $a->fresh()->status);
    }

    public function test_service_duration_suggester_uses_median_of_done_stops_and_feeds_the_report(): void {
        [$driver, $tour, $a, $b] = $this->runningTour();
        $typeId = (int) $a->entry_type_id;
        $service = app(TourService::class);
        $durations = [20, 40, 60];
        foreach ([$a, $b] as $i => $entry) {
            $stop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $entry->id)->firstOrFail();
            $service->arriveAtStop($stop, $driver, CarbonImmutable::parse('2026-06-01 08:00:00'));
            $service->departStop($stop, $driver, TourStop::OUTCOME_DONE, CarbonImmutable::parse('2026-06-01 08:00:00')->addMinutes($durations[$i]));
        }
        $this->assertNull(app(ServiceDurationSuggester::class)->suggestMinutes($typeId), 'Unter drei Werten kein Erfahrungswert.');

        $c = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => 13.3, 'entry_type_id' => $typeId]);
        $service->assignOrders($tour, [$a->id, $b->id, $c->id]);
        $stopC = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $c->id)->firstOrFail();
        $service->arriveAtStop($stopC, $driver, CarbonImmutable::parse('2026-06-01 09:00:00'));
        $service->departStop($stopC, $driver, TourStop::OUTCOME_DONE, CarbonImmutable::parse('2026-06-01 10:00:00'));

        $suggestion = app(ServiceDurationSuggester::class)->suggest($typeId);
        $this->assertSame(['minutes' => 40, 'samples' => 3, 'scope' => 'type'], $suggestion);

        $this->actingAs($driver)->get(route('reports.tour-plan-actual', ['from' => '2026-06-01', 'to' => '2026-06-01']))
            ->assertOk()
            ->assertSee('Touren Plan-Ist');
    }
}
