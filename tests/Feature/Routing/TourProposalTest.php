<?php

namespace Tests\Feature\Routing;

use App\Enums\Tour\TourProposalStatus;
use App\Models\Diary\{DiaryEntry, TourProposal, TourStop};
use App\Models\Platform\User;
use App\Services\Routing\{TourProposalService, TourService};
use App\Support\Tz;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1174–1177: Tourvorschlag anfordern, rechnen, übernehmen (Stopp- und Auftragszeiten), verwerfen. */
class TourProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    /** @return array{0: User, 1: \App\Models\Diary\Tour, 2: DiaryEntry, 3: DiaryEntry, 4: DiaryEntry} */
    private function tourWithThreeStops(): array {
        $driver = User::factory()->user()->create(['organization_id' => $this->organization->id, 'home_lat' => 52.5, 'home_lng' => 13.0, 'home_address' => 'Zuhause']);
        $service = app(TourService::class);
        $tour = $service->createDraft($driver, CarbonImmutable::parse('2026-06-01'));
        $make = fn(float $lng, array $extra = []) => DiaryEntry::factory()->service()->create([
            'organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => $lng, 'service_minutes' => 30,
        ] + $extra);
        $far = $make(13.4);
        $mid = $make(13.2, ['time_window_start' => '08:00', 'time_window_end' => '09:00']);
        $near = $make(13.1);
        $service->assignOrders($tour, [$far->id, $mid->id, $near->id]);

        return [$driver, $tour->fresh(), $far, $mid, $near];
    }

    public function test_assigning_orders_keeps_stop_rows_in_sync(): void {
        [, $tour, $far, $mid, $near] = $this->tourWithThreeStops();

        $this->assertSame([$far->id, $mid->id, $near->id], $tour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        app(TourService::class)->assignOrders($tour, [$near->id, $far->id]);
        $this->assertSame([$near->id, $far->id], $tour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        $this->assertSame([1, 2], $tour->stops()->pluck('position')->map(fn($p) => (int) $p)->all());
    }

    public function test_proposal_is_computed_and_adoption_writes_stop_and_order_times(): void {
        [$driver, $tour, $far, $mid, $near] = $this->tourWithThreeStops();
        $tz = Tz::ofOrganization($this->organization);

        $this->actingAs($driver)->post(route('tours.proposals.store', $tour))->assertRedirect();
        $proposal = TourProposal::query()->firstOrFail();
        // Queue läuft in Tests synchron → der Vorschlag ist gerechnet.
        $this->assertSame(TourProposalStatus::Open, $proposal->status);
        $this->assertSame('heuristic', $proposal->solver);
        $this->assertTrue($proposal->is_estimate);
        $this->assertSame(3, $proposal->metrics['after']['stops']);
        $this->assertSame(0, $proposal->metrics['after']['unassigned']);
        $this->assertSame($tour->id, $proposal->tour_id);
        $this->assertSame('tour.proposal_requested', $tour->journal()->first()?->event);

        $this->actingAs($driver)->get(route('tour-proposals.show', $proposal))->assertOk()->assertSee('Vorschlag');

        $this->actingAs($driver)->post(route('tour-proposals.adopt', $proposal))->assertRedirect(route('tours.show', $tour));

        $proposal->refresh();
        $tour->refresh();
        $this->assertSame(TourProposalStatus::Adopted, $proposal->status);
        $this->assertSame((int) $driver->id, (int) $proposal->decided_by);
        // Geografische Reihenfolge near, mid, far hält das Fenster 08:00–09:00 des mittleren Stopps (Ankunft 08:50).
        $this->assertSame([$near->id, $mid->id, $far->id], $tour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        $this->assertNotNull($tour->planned_start_at);
        $this->assertSame('08:00', $tour->planned_start_at->setTimezone($tz)->format('H:i'));

        $midStop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $mid->id)->firstOrFail();
        $this->assertNotNull($midStop->planned_arrival_at);
        $this->assertSame('08:10', $near->fresh()->start_at?->setTimezone($tz)->format('H:i'));
        $this->assertSame('08:50', $mid->fresh()->start_at?->setTimezone($tz)->format('H:i'));
        $this->assertSame('09:20', $mid->fresh()->end_at?->setTimezone($tz)->format('H:i'));
        $this->assertTrue($mid->fresh()->start_at->greaterThanOrEqualTo($near->fresh()->end_at), 'Der zweite Stopp beginnt nach dem ersten.');
        $this->assertTrue($far->fresh()->start_at->greaterThan($mid->fresh()->end_at));
        $this->assertSame('tour.proposal_adopted', $tour->journal()->get()->last()?->event);
        $this->assertGreaterThan(0.0, (float) $tour->planned_distance_km);
    }

    public function test_proposal_marks_stops_without_coordinates_as_unassigned(): void {
        [$driver, $tour] = $this->tourWithThreeStops();
        $unlocated = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => null, 'address_lng' => null]);
        app(TourService::class)->assignOrders($tour, [...$tour->stops()->pluck('diary_entry_id')->all(), $unlocated->id]);

        $proposal = app(TourProposalService::class)->request($tour->fresh(), $driver)->fresh();

        $this->assertSame(TourProposalStatus::Open, $proposal->status);
        $this->assertSame(1, $proposal->metrics['after']['unassigned']);
        $this->assertSame('no_coordinates', $proposal->plan['unassigned'][0]['reason']);

        app(TourProposalService::class)->adopt($proposal, $driver);
        // Der Stopp ohne Koordinaten bleibt in der Tour, am Ende, ohne Planzeit.
        $stop = TourStop::query()->where('tour_id', $tour->id)->where('diary_entry_id', $unlocated->id)->firstOrFail();
        $this->assertSame(4, $stop->position);
        $this->assertNull($stop->planned_arrival_at);
    }

    public function test_reject_requires_a_note_and_records_it(): void {
        [$driver, $tour] = $this->tourWithThreeStops();
        $proposal = app(TourProposalService::class)->request($tour, $driver)->fresh();

        $this->actingAs($driver)->post(route('tour-proposals.reject', $proposal), [])->assertSessionHasErrors('decision_note');
        $this->actingAs($driver)->post(route('tour-proposals.reject', $proposal), ['decision_note' => 'Kunde wünscht Nachmittag'])->assertRedirect(route('tours.show', $tour));

        $proposal->refresh();
        $this->assertSame(TourProposalStatus::Rejected, $proposal->status);
        $this->assertSame('Kunde wünscht Nachmittag', $proposal->decision_note);
        $this->assertSame('tour.proposal_rejected', $tour->journal()->get()->last()?->event);
        // Verworfen ist endgültig.
        $this->actingAs($driver)->post(route('tour-proposals.adopt', $proposal))->assertSessionHasErrors('proposal');
    }

    public function test_foreign_user_cannot_see_or_adopt_a_proposal(): void {
        [$driver, $tour] = $this->tourWithThreeStops();
        $proposal = app(TourProposalService::class)->request($tour, $driver)->fresh();
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($other)->get(route('tour-proposals.show', $proposal))->assertForbidden();
        $this->actingAs($other)->post(route('tour-proposals.adopt', $proposal))->assertForbidden();
    }

    public function test_edit_form_sets_start_time_and_site_anchor(): void {
        [$driver, $tour] = $this->tourWithThreeStops();
        $site = \App\Models\Facility\Site::factory()->create(['organization_id' => $this->organization->id, 'name' => 'Werkhof', 'geo_lat' => 52.6, 'geo_lng' => 13.05]);

        $this->actingAs($driver)->put(route('tours.update', $tour), [
            'user_id' => \App\Support\Sqid::encode(User::class, (int) $driver->id),
            'tour_date' => '2026-06-01',
            'planned_start_time' => '07:30',
            'start_site_id' => $site->sqid,
        ])->assertRedirect();

        $tour->refresh();
        $this->assertSame('07:30', $tour->planned_start_at?->setTimezone(Tz::ofOrganization($this->organization))->format('H:i'));
        $this->assertSame('52.6000000', (string) $tour->start_lat);
        $this->assertSame((int) $site->id, (int) $tour->start_site_id);
    }

    /** MVP-1180: Rest einer laufenden Tour neu planen — erledigte Stopps bleiben vorn, der Rest wird ab der Plan-Position gelöst. */
    public function test_remainder_replan_keeps_done_stops_and_reorders_the_rest(): void {
        [$driver, $tour, $far, $mid, $near] = $this->tourWithThreeStops();
        $service = app(TourService::class);
        $service->start($tour);
        // Erster Stopp (far) ist erledigt — die Rest-Tour startet dort.
        $far->forceFill(['status' => \App\Enums\Diary\Status::Done])->save();
        $mid->forceFill(['time_window_start' => null, 'time_window_end' => null])->save();

        // Ohne laufende Tour kein Rest-Vorschlag.
        $draft = $service->createDraft($driver, CarbonImmutable::parse('2026-06-02'));
        $this->actingAs($driver)->post(route('tours.proposals.store', $draft), ['remainder' => 1])->assertSessionHasErrors('proposal');

        $this->actingAs($driver)->post(route('tours.proposals.store', $tour), ['remainder' => 1])->assertRedirect();
        $proposal = TourProposal::query()->where('tour_id', $tour->id)->latest('id')->firstOrFail();
        $this->assertSame(TourProposalStatus::Open, $proposal->status);
        $this->assertSame('remainder', $proposal->problem['kind']);
        $this->assertSame(2, $proposal->metrics['after']['stops'], 'Nur die offenen Stopps werden geplant.');

        app(TourProposalService::class)->adopt($proposal, $driver);

        // far bleibt Position 1; von far (13.4) aus liegt mid (13.2) vor near (13.1).
        $this->assertSame([$far->id, $mid->id, $near->id], $tour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        $this->assertSame('tour.replanned', $tour->journal()->get()->last()?->event);
        $this->assertNotNull($mid->fresh()->start_at);
        $this->assertTrue($near->fresh()->start_at->greaterThan($mid->fresh()->end_at));
    }

    /** Sicherheitsaudit 2026-10-21, li-7: eine veraltete zweite Übernahme legt nichts doppelt an. */
    public function test_a_stale_second_adoption_is_rejected(): void {
        [$driver, $tour] = $this->tourWithThreeStops();
        $proposal = app(TourProposalService::class)->request($tour, $driver)->fresh();
        $stale = TourProposal::query()->findOrFail($proposal->id);

        app(TourProposalService::class)->adopt($proposal, $driver);
        $events = $tour->journal()->count();

        try {
            app(TourProposalService::class)->adopt($stale, $driver);
            $this->fail('Ein übernommener Vorschlag darf nicht erneut übernommen werden.');
        } catch (\RuntimeException|\Illuminate\Validation\ValidationException) {
        }
        $this->assertSame($events, $tour->journal()->count());
    }
}
