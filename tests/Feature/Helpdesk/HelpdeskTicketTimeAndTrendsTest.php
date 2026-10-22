<?php
/*
 * Created on   : Thu Oct 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskTicketTimeAndTrendsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\Project\ProjectStatus;
use App\Models\Customer\Customer;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Models\ServiceTicket\ServiceTicket;
use App\Models\Time\TimeEntry;
use App\Services\Invoicing\BillableTimeAggregator;
use App\Services\ServiceTicket\{HelpdeskMetricsService, ServiceTicketService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Zeiten am Ticket mit Summe und Ticketbezug in der Abrechnung (MVP-1259)
 * sowie Monatsverläufe im Helpdesk-Bericht (MVP-1260).
 */
final class HelpdeskTicketTimeAndTrendsTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $lead;

    private User $worker;

    private Project $project;

    private ServiceTicket $ticket;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $this->worker = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->project = Project::create([
            'organization_id' => $this->organization->id, 'customer_id' => $customer->id,
            'name' => 'Betreuung', 'status' => ProjectStatus::Active->value, 'created_by' => $this->lead->id,
        ]);
        $this->ticket = app(ServiceTicketService::class)->create($this->organization, $this->lead, [
            'title' => 'Drucker offline', 'customer_id' => $customer->id, 'project_id' => $this->project->id,
        ]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_time_is_booked_on_the_ticket_from_the_project_dialog(): void {
        $this->actingAs($this->worker)
            ->get(route('projects.time-entries.create', ['project' => $this->project, 'service_ticket' => $this->ticket->sqid]))
            ->assertOk()
            ->assertSee('<option value="' . $this->ticket->sqid . '" selected', false);

        $this->actingAs($this->worker)->post(route('projects.time-entries.store', $this->project), [
            'date' => now()->toDateString(), 'minutes' => 45, 'description' => 'Treiber neu', 'service_ticket_id' => $this->ticket->sqid,
        ])->assertRedirect();

        $this->assertSame((int) $this->ticket->id, (int) TimeEntry::query()->where('description', 'Treiber neu')->value('service_ticket_id'));
    }

    public function test_ticket_sums_only_the_time_the_viewer_may_see(): void {
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->worker->id, 'service_ticket_id' => $this->ticket->id, 'minutes' => 30, 'billable' => true]);
        $other = User::factory()->user()->create(['organization_id' => $this->organization->id]);
        TimeEntry::factory()->create(['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $other->id, 'service_ticket_id' => $this->ticket->id, 'minutes' => 90, 'billable' => true]);

        $this->actingAs($this->lead)->get(route('service-tickets.show', $this->ticket))
            ->assertOk()
            ->assertSee('data-ticket-time', false)
            ->assertSee(route('projects.time-entries.create', ['project' => $this->project, 'service_ticket' => $this->ticket->sqid]), false)
            ->assertSee($other->name);

        $workerView = $this->ticket->timeEntries()->visibleTo($this->worker)->sum('minutes');
        $this->assertSame(30, (int) $workerView);
    }

    public function test_billing_keeps_tickets_apart_and_names_them(): void {
        $base = ['organization_id' => $this->organization->id, 'project_id' => $this->project->id, 'user_id' => $this->worker->id, 'date' => '2026-10-20'];
        $onTicket = TimeEntry::factory()->create([...$base, 'minutes' => 30, 'service_ticket_id' => $this->ticket->id, 'description' => 'Treiber']);
        $plain = TimeEntry::factory()->create([...$base, 'minutes' => 60]);

        $blocks = app(BillableTimeAggregator::class)->aggregate(TimeEntry::query()->whereKey([$onTicket->id, $plain->id])->get());

        $this->assertCount(2, $blocks);
        $ticketBlock = $blocks->first(fn ($b) => $b->ticketNo !== null);
        $this->assertSame($this->ticket->ticket_no, $ticketBlock->ticketNo);
        $this->assertSame(__('Ticket :no', ['no' => $this->ticket->ticket_no]) . ' · Treiber', $ticketBlock->withTicketPrefix('Treiber'));
        $this->assertSame('Leistung', $blocks->first(fn ($b) => $b->ticketNo === null)->withTicketPrefix('Leistung'));
    }

    public function test_monthly_trends_cover_twelve_months_with_counts(): void {
        Carbon::setTestNow('2026-10-22 12:00:00');
        $tickets = app(ServiceTicketService::class);
        $tickets->create($this->organization, $this->lead, ['title' => 'Alt', 'reported_at' => '2026-08-05 09:00:00']);
        $tickets->create($this->organization, $this->lead, ['title' => 'Neu', 'reported_at' => '2026-10-02 09:00:00']);

        $trends = app(HelpdeskMetricsService::class)->monthlyTrends(Carbon::parse('2026-10-31'));

        $this->assertCount(12, $trends);
        $this->assertSame('2025-11', $trends[0]['month']);
        $byMonth = collect($trends)->keyBy('month');
        $this->assertSame(1, $byMonth['2026-08']['created']);
        $this->assertSame(2, $byMonth['2026-10']['created']);

        $this->actingAs($this->lead)->get(route('helpdesk.reports.index'))
            ->assertOk()
            ->assertSee(__('helpdesk.report.trendTable'));
        $this->actingAs($this->lead)->get(route('helpdesk.reports.csv', ['metric' => 'trends']))
            ->assertOk();
    }
}
