<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskDomainTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketStatus, TicketCloseCode, TicketSeverity};
use App\Models\{Organization, ServiceTicket, SlaClockSegment, SlaContract, User};
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 065, P1 (MVP-151): additive Wartezustände (Alt-Ticket durchläuft
 * alle neuen Übergänge), Warten mit Pflichtgrund+Wiedervorlage, SLA-Pause
 * NUR bei deklariertem Grund (pause_rules) inkl. Fristverschiebung beim
 * Fortsetzen, Wiederöffnung nur mit Grund und ohne Umschreiben der
 * SLA-Historie, neue Enums vollständig.
 */
final class HelpdeskDomainTest extends TestCase {
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

    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::InProgress,
            'assigned_to_user_id' => $this->agent->id,
            ...$overrides,
        ]);
    }

    public function test_legacy_ticket_walks_through_all_new_transitions(): void {
        $service = app(ServiceTicketService::class);
        $ticket = $this->ticket();

        // in_progress → waiting_customer → in_progress → paused → in_progress → done → in_progress (reopen) → done → closed
        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::WaitingCustomer, 'Rückfrage offen', now()->addDays(2));
        $this->assertSame(ServiceTicketStatus::WaitingCustomer, $ticket->status);
        $this->assertNotNull($ticket->wait_until);

        $ticket = $service->resume($ticket, $this->agent);
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->wait_reason);

        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::Paused, 'Ersatzteil bestellt', now()->addWeek());
        $ticket = $service->resume($ticket, $this->agent);

        $ticket = $service->transition($ticket, $this->agent, ServiceTicketStatus::Done);
        $ticket = $service->reopen($ticket, $this->agent, 'Kunde meldet Folgefehler');
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);

        $ticket = $service->transition($ticket, $this->agent, ServiceTicketStatus::Done);
        $ticket = $service->transition($ticket, $this->agent, ServiceTicketStatus::Closed);
        $this->assertSame(ServiceTicketStatus::Closed, $ticket->status);
    }

    public function test_wait_requires_reason(): void {
        $service = app(ServiceTicketService::class);
        $ticket = $this->ticket();

        $this->expectException(\InvalidArgumentException::class);
        $service->wait($ticket, $this->agent, ServiceTicketStatus::WaitingCustomer, '   ', now()->addDay());
    }

    public function test_sla_pauses_only_for_declared_reasons_and_shifts_deadline(): void {
        $service = app(ServiceTicketService::class);

        $contract = SlaContract::factory()->create([
            'organization_id' => $this->org->id,
            'pause_rules' => ['waiting_customer'],
        ]);

        Carbon::setTestNow('2026-07-06 09:00:00');
        $due = Carbon::parse('2026-07-08 09:00:00');
        $ticket = $this->ticket([
            'sla_contract_id' => $contract->id,
            'resolution_due_at' => $due,
            'resolved_at' => null,
        ]);

        // Deklarierter Grund → Segment + Fristverschiebung um die Pausendauer.
        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::WaitingCustomer, 'Info fehlt', now()->addDays(3));
        $this->assertSame(1, SlaClockSegment::query()->where('service_ticket_id', $ticket->id)->whereNull('paused_to')->count());

        Carbon::setTestNow('2026-07-07 09:00:00'); // 24h Pause
        $ticket = $service->resume($ticket, $this->agent);
        $this->assertSame(0, SlaClockSegment::query()->whereNull('paused_to')->count());
        $this->assertTrue($ticket->resolution_due_at->equalTo($due->copy()->addMinutes(1440)));

        // NICHT deklarierter Grund → keine Pause, Frist unverändert.
        $shifted = $ticket->resolution_due_at->copy();
        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::Paused, 'Intern pausiert', now()->addDay());
        $this->assertSame(1, SlaClockSegment::query()->count(), 'Kein neues Segment für nicht deklarierten Grund.');
        $ticket = $service->resume($ticket, $this->agent);
        $this->assertTrue($ticket->resolution_due_at->equalTo($shifted));
    }

    public function test_reopen_requires_reason_and_keeps_sla_history(): void {
        $service = app(ServiceTicketService::class);

        Carbon::setTestNow('2026-07-06 09:00:00');
        $ticket = $this->ticket();
        $ticket = $service->transition($ticket, $this->agent, ServiceTicketStatus::Done);
        $resolvedAt = $ticket->resolved_at->copy();

        try {
            $service->reopen($ticket, $this->agent, '');
            $this->fail('Wiederöffnung ohne Grund wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        Carbon::setTestNow('2026-07-07 15:00:00');
        $ticket = $service->reopen($ticket, $this->agent, 'Folgefehler');

        // Historische Zeitstempel bleiben unverändert (DoD 065).
        $this->assertTrue($ticket->resolved_at->equalTo($resolvedAt));
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);

        // Aus reported darf NICHT wiedereröffnet werden.
        $fresh = $this->ticket(['status' => ServiceTicketStatus::Reported]);
        $this->expectException(\App\Exceptions\ServiceTicketException::class);
        $service->reopen($fresh, $this->agent, 'Grund');
    }

    public function test_new_enums_are_complete(): void {
        $this->assertSame(
            ['reported', 'triaged', 'scheduled', 'in_progress', 'done', 'accepted', 'closed', 'rejected', 'waiting_customer', 'waiting_external', 'paused'],
            array_map(fn(ServiceTicketStatus $s) => $s->value, ServiceTicketStatus::cases()),
        );
        $this->assertSame(['incident', 'service_request', 'question'], array_map(fn($c) => $c->value, ServiceTicketKind::cases()));
        $this->assertSame(['solved', 'workaround', 'duplicate', 'no_fault', 'rejected', 'other'], array_map(fn($c) => $c->value, TicketCloseCode::cases()));
        $this->assertSame([1, 2, 3], array_map(fn($c) => $c->value, TicketSeverity::cases()));

        foreach (ServiceTicketStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
        }

        // Ticket-Casts: kind/impact/urgency/close_code als Enums nutzbar.
        $ticket = $this->ticket([
            'kind' => 'incident',
            'impact' => 3,
            'urgency' => 2,
            'close_code' => 'workaround',
        ]);
        $this->assertSame(ServiceTicketKind::Incident, $ticket->kind);
        $this->assertSame(TicketSeverity::High, $ticket->impact);
        $this->assertSame(TicketCloseCode::Workaround, $ticket->fresh()->close_code);
    }
}
