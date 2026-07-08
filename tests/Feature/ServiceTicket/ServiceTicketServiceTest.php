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
use App\Models\{Customer, DiaryEntry, Organization, Project, SlaContract, User};
use App\Services\Numbering\NumberSequenceService;
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

        $this->service = new ServiceTicketService(new TicketStatusMachine, new SlaTimer, new NumberSequenceService);
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

    public function test_create_links_diary_entry_and_inherits_customer_and_project(): void {
        $customer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $project = Project::factory()->create(['organization_id' => $this->org->id, 'customer_id' => $customer->id]);
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->actor->id,
            'customer_id' => $customer->id,
            'project_id' => $project->id,
        ]);

        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'Aus Auftrag',
            'priority' => ServiceTicketPriority::Normal->value,
            'diary_entry_id' => $entry->id,
        ]);

        $this->assertSame($entry->id, $ticket->diary_entry_id);
        $this->assertSame($customer->id, $ticket->customer_id); // aus dem Auftrag vorbefüllt
        $this->assertSame($project->id, $ticket->project_id);   // aus dem Auftrag vorbefüllt
        $this->assertTrue($entry->serviceTickets()->whereKey($ticket->id)->exists());
    }

    public function test_explicit_customer_wins_over_diary_entry_prefill(): void {
        $entryCustomer = Customer::factory()->create(['organization_id' => $this->org->id]);
        $entry = DiaryEntry::factory()->create([
            'organization_id' => $this->org->id,
            'user_id' => $this->actor->id,
            'customer_id' => $entryCustomer->id,
        ]);
        $explicit = Customer::factory()->create(['organization_id' => $this->org->id]);

        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'X',
            'priority' => ServiceTicketPriority::Normal->value,
            'diary_entry_id' => $entry->id,
            'customer_id' => $explicit->id,
        ]);

        $this->assertSame($explicit->id, $ticket->customer_id); // explizite Angabe schlägt Vorbefüllung
        $this->assertSame($entry->id, $ticket->diary_entry_id);
    }

    public function test_foreign_diary_entry_is_not_linked(): void {
        $otherOrg = Organization::factory()->create();
        $foreignUser = User::factory()->create(['organization_id' => $otherOrg->id]);
        $foreignEntry = DiaryEntry::factory()->create([
            'organization_id' => $otherOrg->id,
            'user_id' => $foreignUser->id,
        ]);

        $ticket = $this->service->create($this->org, $this->actor, [
            'title' => 'X',
            'priority' => ServiceTicketPriority::Normal->value,
            'diary_entry_id' => $foreignEntry->id,
        ]);

        $this->assertNull($ticket->diary_entry_id); // fremder Eintrag → keine Verknüpfung (Mandantengrenze)
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
