<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ServiceTicketServiceTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketStatus};
use App\Exceptions\ServiceTicketException;
use App\Models\{Organization, SlaContract, User};
use App\Services\ServiceTicket\{ServiceTicketService, SlaTimer, TicketStatusMachine};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceTicketServiceTest extends TestCase {
    use RefreshDatabase;

    private ServiceTicketService $service;

    private Organization $org;

    private User $actor;

    protected function setUp(): void {
        parent::setUp();

        $this->service = new ServiceTicketService(new TicketStatusMachine, new SlaTimer);
        $this->org = Organization::factory()->create();
        $this->actor = User::factory()->geschaeftsfuehrung()->create([
            'organization_id' => $this->org->id,
        ]);
        $this->actingAs($this->actor);
        app()->instance('currentOrganization', $this->org);
    }

    public function test_create_generates_ticket_no_and_audits(): void {
        Carbon::setTestNow('2026-06-01 09:00:00');

        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'Heizung defekt',
            'priority' => ServiceTicketPriority::High->value,
        ]);

        $this->assertSame('ST-2026-00001', $ticket->ticket_no);
        $this->assertSame(ServiceTicketStatus::Reported, $ticket->status);
        $this->assertSame(ServiceTicketPriority::High, $ticket->priority);
        $this->assertSame($this->actor->id, $ticket->reported_by_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'service_ticket.created',
            'auditable_id' => $ticket->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_create_applies_default_sla_contract_deadlines(): void {
        Carbon::setTestNow('2026-06-01 09:00:00');

        SlaContract::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => null,
            'is_default' => true,
            'is_active' => true,
            'priority_table' => [
                'normal' => ['reaction_minutes' => 60, 'resolution_minutes' => 480],
            ],
        ]);

        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'Anfrage',
            'priority' => ServiceTicketPriority::Normal->value,
        ]);

        $this->assertNotNull($ticket->sla_contract_id);
        $this->assertSame('2026-06-01 10:00:00', $ticket->reaction_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-01 17:00:00', $ticket->resolution_due_at?->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_transition_enforces_status_machine(): void {
        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'X',
            'priority' => ServiceTicketPriority::Normal->value,
        ]);

        $this->expectException(ServiceTicketException::class);
        $this->service->transition($ticket, $this->actor, ServiceTicketStatus::Accepted);
    }

    public function test_transition_to_in_progress_requires_assignee(): void {
        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'X',
            'priority' => ServiceTicketPriority::Normal->value,
        ]);

        $this->expectException(ServiceTicketException::class);
        $this->service->transition($ticket, $this->actor, ServiceTicketStatus::InProgress);
    }

    public function test_full_lifecycle_stamps_timestamps(): void {
        Carbon::setTestNow('2026-06-01 09:00:00');
        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'Reparatur',
            'priority' => ServiceTicketPriority::Normal->value,
        ]);

        $this->service->assign($ticket, $this->actor, $this->actor->id);
        $ticket->refresh();

        Carbon::setTestNow('2026-06-01 10:00:00');
        $ticket = $this->service->transition($ticket, $this->actor, ServiceTicketStatus::InProgress);
        $this->assertNotNull($ticket->started_at);
        $this->assertNotNull($ticket->acknowledged_at);

        Carbon::setTestNow('2026-06-01 12:00:00');
        $ticket = $this->service->transition($ticket, $this->actor, ServiceTicketStatus::Done);
        $this->assertNotNull($ticket->resolved_at);

        Carbon::setTestNow('2026-06-01 13:00:00');
        $ticket = $this->service->transition($ticket, $this->actor, ServiceTicketStatus::Accepted);
        $this->assertNotNull($ticket->accepted_at);

        Carbon::setTestNow('2026-06-01 14:00:00');
        $ticket = $this->service->transition($ticket, $this->actor, ServiceTicketStatus::Closed);
        $this->assertNotNull($ticket->closed_at);

        Carbon::setTestNow();
    }

    public function test_ticket_no_increments_per_year_per_org(): void {
        Carbon::setTestNow('2026-06-01 09:00:00');
        $a = $this->service->create($this->org, $this->actor, ['title' => 'A', 'priority' => ServiceTicketPriority::Normal->value]);
        $b = $this->service->create($this->org, $this->actor, ['title' => 'B', 'priority' => ServiceTicketPriority::Normal->value]);

        $this->assertSame('ST-2026-00001', $a->ticket_no);
        $this->assertSame('ST-2026-00002', $b->ticket_no);

        Carbon::setTestNow();
    }
}
