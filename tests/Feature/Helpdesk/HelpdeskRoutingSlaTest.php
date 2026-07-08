<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskRoutingSlaTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\Notification\NotificationEvent;
use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Organization, ServiceQueue, ServiceTicket, SlaContract, TicketRoutingRule, TicketRuleExecution, User};
use App\Services\ServiceTicket\{ServiceTicketService, TicketRoutingService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 065, P3 (MVP-153): Regel-Engine deterministisch (Position,
 * erste zutreffende Regel je Aktionstyp) mit Pflicht-Protokoll, Dry-Run
 * ohne Änderung, sla_snapshot-Immunität gegen Vertragsänderungen,
 * Wiedervorlage-Scan mit Dedup.
 */
final class HelpdeskRoutingSlaTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_routing_applies_first_match_per_action_and_logs(): void {
        $queueA = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Default', 'is_default' => true]);
        $queueB = ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Störungen']);

        TicketRoutingRule::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Priorität für Incidents',
            'position' => 1,
            'conditions' => ['kind' => 'incident'],
            'actions' => ['set_priority' => 'high'],
        ]);
        TicketRoutingRule::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Incident-Queue',
            'position' => 2,
            'conditions' => ['kind' => 'incident'],
            'actions' => ['set_priority' => 'low', 'set_queue' => $queueB->id],
        ]);

        $ticket = app(ServiceTicketService::class)->create($this->org, $this->agent, [
            'title' => 'Server down',
            'kind' => 'incident',
        ]);

        // Erste Regel gewinnt für set_priority; set_queue kommt aus Regel 2.
        $this->assertSame('high', $ticket->priority->value);
        $this->assertSame((int) $queueB->id, (int) $ticket->queue_id);
        $this->assertNotSame((int) $queueA->id, (int) $ticket->queue_id);

        $executions = TicketRuleExecution::query()->where('service_ticket_id', $ticket->id)->orderBy('id')->get();
        $this->assertCount(2, $executions);
        $this->assertSame(['kind' => 'incident'], $executions[0]->matched_conditions);
        $this->assertSame(['set_priority' => 'high'], $executions[0]->applied_actions);
        $this->assertSame(['set_queue' => $queueB->id], $executions[1]->applied_actions);
    }

    public function test_dry_run_logs_but_changes_nothing(): void {
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Default', 'is_default' => true]);
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'kind' => 'incident',
            'priority' => 'normal',
        ]);
        TicketRoutingRule::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Test',
            'position' => 1,
            'conditions' => ['kind' => 'incident'],
            'actions' => ['set_priority' => 'high'],
        ]);

        $log = app(TicketRoutingService::class)->apply($ticket->fresh(), dryRun: true);

        $this->assertCount(1, $log);
        $this->assertSame('normal', $ticket->fresh()->priority->value, 'Dry-Run ändert nichts.');
        $this->assertTrue(TicketRuleExecution::query()->where('dry_run', true)->exists());
    }

    public function test_sla_snapshot_is_immune_to_contract_changes(): void {
        Carbon::setTestNow('2026-07-06 09:00:00');
        $contract = SlaContract::factory()->create([
            'organization_id' => $this->org->id,
            'customer_id' => null,
            'is_default' => true,
            'is_active' => true,
            'priority_table' => ['normal' => ['reaction_minutes' => 60, 'resolution_minutes' => 480]],
        ]);
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Default', 'is_default' => true]);

        $ticket = app(ServiceTicketService::class)->create($this->org, $this->agent, [
            'title' => 'Mit SLA',
            'priority' => 'normal',
        ]);
        $due = $ticket->resolution_due_at->copy();
        $this->assertSame(60, $ticket->sla_snapshot['priority_table']['normal']['reaction_minutes']);

        // Vertragsänderung deutet NIE um: Frist + Snapshot bleiben.
        $contract->update(['priority_table' => ['normal' => ['reaction_minutes' => 5, 'resolution_minutes' => 10]]]);
        $fresh = $ticket->fresh();
        $this->assertTrue($fresh->resolution_due_at->equalTo($due));
        $this->assertSame(60, $fresh->sla_snapshot['priority_table']['normal']['reaction_minutes']);
    }

    public function test_admin_uis_work_with_manage_right(): void {
        ServiceQueue::query()->create(['organization_id' => $this->org->id, 'name' => 'Default', 'is_default' => true]);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->org->id, 'kind' => 'incident']);
        TicketRoutingRule::query()->create([
            'organization_id' => $this->org->id,
            'name' => 'Regel',
            'position' => 1,
            'conditions' => ['kind' => 'incident'],
            'actions' => ['set_priority' => 'high'],
        ]);

        $this->actingAs($this->agent)->get(route('helpdesk.routing.index'))->assertOk()->assertSee('Regel');

        // Dry-Run über die UI: protokolliert, ändert nichts.
        $this->actingAs($this->agent)
            ->post(route('helpdesk.routing.dry-run'), ['ticket_no' => $ticket->ticket_no])
            ->assertRedirect();
        $this->assertTrue(TicketRuleExecution::query()->where('dry_run', true)->exists());

        // SLA-CRUD: anlegen mit pause_rules + OLA.
        $this->actingAs($this->agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'GOLD',
                'label' => 'Gold-SLA',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480} }',
                'pause_rules' => ['waiting_customer'],
                'is_default' => '1',
            ])->assertRedirect(route('sla-contracts.index'));
        $contract = SlaContract::query()->where('code', 'GOLD')->firstOrFail();
        $this->assertSame(['waiting_customer'], $contract->pause_rules);
        $this->assertTrue($contract->is_default);
    }

    public function test_waiting_scan_notifies_owner_once(): void {
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::WaitingCustomer,
            'assigned_to_user_id' => $this->agent->id,
            'wait_reason' => 'Info fehlt',
            'wait_until' => now()->subHour(),
            'wait_owner_id' => $this->agent->id,
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $notifications = $this->agent->notifications()->get()
            ->filter(fn($n) => (($n->data['event'] ?? null) === NotificationEvent::TicketWaitingExpired->value));
        $this->assertCount(1, $notifications, 'Dedup: genau eine Wiedervorlage-Benachrichtigung.');
        $this->assertNotNull($ticket->fresh());
    }
}
