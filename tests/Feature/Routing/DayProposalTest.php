<?php

namespace Tests\Feature\Routing;

use App\Enums\Tour\{TourProposalStatus, TourStatus};
use App\Enums\User\Permission;
use App\Models\Customer\Customer;
use App\Models\Diary\{DiaryEntry, Tour, TourProposal};
use App\Models\Platform\User;
use App\Services\Routing\TourProposalService;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Database\Seeders\EntryTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/** MVP-1179: Tagesvorschlag der Leitstelle — Fahrer und Aufträge eines Tages, Übernahme legt Touren an. */
class DayProposalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    protected function setUp(): void {
        parent::setUp();
        $this->seed(EntryTypeSeeder::class);
        $this->setUpOrganization();
        Config::set('routing.osrm.base_url', 'http://does-not-exist.invalid');
        Config::set('timesheet.travel.auto_create_time_entry', false);
    }

    private function order(float $lng, array $extra = []): DiaryEntry {
        return DiaryEntry::factory()->service()->create(array_merge([
            'organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => 52.5, 'address_lng' => $lng, 'service_minutes' => 30,
        ], $extra));
    }

    public function test_day_plan_page_and_proposal_split_orders_between_two_drivers(): void {
        $dispatcher = User::factory()->admin()->create(['organization_id' => $this->organization->id]);
        $west = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'West', 'home_lat' => 52.5, 'home_lng' => 13.0]);
        $east = User::factory()->user()->create(['organization_id' => $this->organization->id, 'name' => 'Ost', 'home_lat' => 52.5, 'home_lng' => 14.0]);
        $w1 = $this->order(13.1);
        $w2 = $this->order(13.2);
        $e1 = $this->order(13.9);
        $e2 = $this->order(13.8);
        // Ohne Koordinaten und ohne Kunde → kein Kandidat.
        DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'address_lat' => null, 'address_lng' => null]);
        // Anderer Tag → kein Kandidat.
        $this->order(13.3, ['scheduled_for' => '2026-06-02']);

        $this->actingAs($dispatcher)->get(route('dispatch.day-plan', ['date' => '2026-06-01']))->assertOk()->assertSee('Tagesvorschlag');

        $this->actingAs($dispatcher)->post(route('dispatch.day-plan.store'), [
            'date' => '2026-06-01',
            'drivers' => [Sqid::encode(User::class, (int) $west->id), Sqid::encode(User::class, (int) $east->id)],
        ])->assertRedirect();

        $proposal = TourProposal::query()->whereNull('tour_id')->firstOrFail();
        $this->assertSame(TourProposalStatus::Open, $proposal->status);
        $this->assertSame(4, $proposal->metrics['after']['stops']);
        $this->assertSame(2, $proposal->metrics['after']['tours']);
        $this->assertSame(0, $proposal->metrics['after']['unassigned']);

        $this->actingAs($dispatcher)->get(route('tour-proposals.show', $proposal))->assertOk()->assertSee('West')->assertSee('Ost');

        $this->actingAs($dispatcher)->post(route('tour-proposals.adopt', $proposal))->assertRedirect(route('dispatch.day-plan', ['date' => '2026-06-01']));

        $westTour = Tour::query()->forUser((int) $west->id)->onDate('2026-06-01')->firstOrFail();
        $eastTour = Tour::query()->forUser((int) $east->id)->onDate('2026-06-01')->firstOrFail();
        $this->assertEqualsCanonicalizing([$w1->id, $w2->id], $westTour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        $this->assertEqualsCanonicalizing([$e1->id, $e2->id], $eastTour->stops()->pluck('diary_entry_id')->map(fn($id) => (int) $id)->all());
        $this->assertSame(TourStatus::Draft, $westTour->status);
        $this->assertNotNull($westTour->planned_start_at);
        $this->assertNotNull($w1->fresh()->start_at);
        $this->assertSame((int) $west->id, (int) $w1->fresh()->assigned_user_id);
        $this->assertSame('tour.proposal_adopted', $westTour->journal()->get()->last()?->event);
        $this->assertSame(TourProposalStatus::Adopted, $proposal->fresh()->status);
    }

    public function test_day_plan_requires_dispatch_manage(): void {
        $user = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $this->assertFalse($user->can(Permission::DispatchManage->value));

        $this->actingAs($user)->get(route('dispatch.day-plan'))->assertForbidden();
        $this->actingAs($user)->post(route('dispatch.day-plan.store'), ['date' => '2026-06-01', 'drivers' => [Sqid::encode(User::class, (int) $user->id)]])->assertForbidden();
    }

    public function test_customer_coordinates_make_an_order_a_candidate(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id, 'address_lat' => 52.5, 'address_lng' => 13.3]);
        $entry = DiaryEntry::factory()->service()->create(['organization_id' => $this->organization->id, 'scheduled_for' => '2026-06-01', 'customer_id' => $customer->id, 'address_lat' => null, 'address_lng' => null]);

        $candidates = app(TourProposalService::class)->dayCandidates(CarbonImmutable::parse('2026-06-01'));

        $this->assertTrue($candidates->contains('id', $entry->id));
    }
}
