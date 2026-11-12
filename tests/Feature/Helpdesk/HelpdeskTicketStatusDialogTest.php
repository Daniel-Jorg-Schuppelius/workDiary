<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskTicketStatusDialogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketCloseCode};
use App\Models\Platform\User;
use App\Models\ServiceTicket\{ServiceTicket, SlaClockSegment, SlaContract};
use App\Services\Integration\LifecycleWebhookPublisher;
use App\Services\Notification\DeadlineScans\DeadlineScanOptions;
use App\Services\Notification\NotificationDispatcher;
use App\Services\ServiceTicket\DeadlineScans\HelpdeskFollowupScans;
use App\Services\ServiceTicket\{HelpdeskMetricsService, TicketConversationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1546, MVP-1547: Statuswechsel über die Route — Lösen und Schließen mit
 * Abschlusscode, Warten mit Grund, Wiedervorlage und SLA-Pause, Fortsetzen,
 * Wiederöffnen nur mit Grund.
 */
final class HelpdeskTicketStatusDialogTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'assigned_to_user_id' => $this->agent->id,
            'status' => ServiceTicketStatus::InProgress->value,
            ...$overrides,
        ]);
    }

    public function test_detail_page_links_the_status_dialog_and_dialog_offers_only_allowed_targets(): void {
        $ticket = $this->ticket();

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee(route('service-tickets.status-form', $ticket), false);

        $this->actingAs($this->agent)->get(route('service-tickets.status-form', $ticket))
            ->assertOk()
            ->assertSee('name="close_code"', false)
            ->assertSee('name="wait_reason"', false)
            ->assertSee('value="done"', false)
            ->assertSee('value="waiting_customer"', false)
            ->assertDontSee('value="accepted"', false)
            ->assertDontSee('name="reopen_reason"', false);
    }

    public function test_resolving_without_close_code_is_rejected(): void {
        $ticket = $this->ticket();

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'done'])
            ->assertSessionHasErrors('close_code');

        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_ticket_can_be_resolved_and_closed_through_the_route(): void {
        $ticket = $this->ticket();
        $this->mock(LifecycleWebhookPublisher::class)->shouldReceive('ticketClosed')->once();

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), [
                'status' => 'done',
                'close_code' => TicketCloseCode::Solved->value,
                'resolution_summary' => 'Netzteil getauscht.',
            ])
            ->assertRedirect(route('service-tickets.show', $ticket))
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        // Gelöst ist die Voraussetzung für Abnahme und Bewertung im Kundenportal.
        $this->assertSame(ServiceTicketStatus::Done, $ticket->status);
        $this->assertSame(TicketCloseCode::Solved, $ticket->close_code);
        $this->assertSame('Netzteil getauscht.', $ticket->resolution_summary);
        $this->assertNotNull($ticket->resolved_at);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'closed'])
            ->assertSessionHasErrors('close_code');

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), [
                'status' => 'closed',
                'close_code' => TicketCloseCode::Workaround->value,
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::Closed, $ticket->status);
        $this->assertSame(TicketCloseCode::Workaround, $ticket->close_code);
        $this->assertSame('Netzteil getauscht.', $ticket->resolution_summary);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.closed', 'auditable_id' => $ticket->id]);

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Netzteil getauscht.');
    }

    public function test_waiting_requires_reason_and_follow_up(): void {
        $ticket = $this->ticket();

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'waiting_customer'])
            ->assertSessionHasErrors(['wait_reason', 'wait_until']);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), [
                'status' => 'waiting_customer',
                'wait_reason' => 'Rückfrage zum Gerät',
                'wait_until' => now()->subDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('wait_until');

        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);
    }

    public function test_waiting_pauses_the_clock_by_contract_and_resume_shifts_the_deadline(): void {
        Carbon::setTestNow('2027-03-01 09:00:00');
        $contract = SlaContract::factory()->create([
            'organization_id' => $this->organization->id,
            'pause_rules' => ['waiting_customer'],
        ]);
        $owner = User::factory()->create(['organization_id' => $this->organization->id]);
        $ticket = $this->ticket([
            'sla_contract_id' => $contract->id,
            'acknowledged_at' => now(),
            'resolution_due_at' => now()->addHours(8),
        ]);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), [
                'status' => 'waiting_customer',
                'wait_reason' => 'Rückfrage zum Gerät',
                'wait_until' => now()->addDay()->format('Y-m-d\TH:i'),
                'wait_owner_id' => $owner->sqid,
            ])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::WaitingCustomer, $ticket->status);
        $this->assertSame('Rückfrage zum Gerät', $ticket->wait_reason);
        $this->assertSame($owner->id, (int) $ticket->wait_owner_id);
        $this->assertNotNull($ticket->wait_until);
        $this->assertSame(1, SlaClockSegment::query()->where('service_ticket_id', $ticket->id)->whereNull('paused_to')->count());

        // Wiedervorlage-Scan findet das Ticket nach Ablauf.
        Carbon::setTestNow('2027-03-03 09:00:00');
        $this->assertSame(1, (new HelpdeskFollowupScans)->run(app(NotificationDispatcher::class), new DeadlineScanOptions(7, 30)));

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'in_progress'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->wait_reason);
        $this->assertSame('2027-03-03 17:00:00', $ticket->resolution_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame(0, SlaClockSegment::query()->where('service_ticket_id', $ticket->id)->whereNull('paused_to')->count());

        // Bericht „Wartezeit nach Grund“ bekommt das geschlossene Segment.
        $report = app(HelpdeskMetricsService::class)->waitingByReason(Carbon::parse('2027-03-01'), Carbon::parse('2027-03-31'));
        $this->assertSame(['waiting_customer' => 48.0], $report);

        Carbon::setTestNow();
    }

    public function test_waiting_without_pause_rule_keeps_the_clock_running(): void {
        $contract = SlaContract::factory()->create(['organization_id' => $this->organization->id, 'pause_rules' => []]);
        $ticket = $this->ticket(['sla_contract_id' => $contract->id, 'resolution_due_at' => now()->addHours(8)]);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), [
                'status' => 'waiting_external',
                'wait_reason' => 'Ersatzteil bestellt',
                'wait_until' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ServiceTicketStatus::WaitingExternal, $ticket->fresh()->status);
        $this->assertSame($this->agent->id, (int) $ticket->fresh()->wait_owner_id);
        $this->assertSame(0, SlaClockSegment::query()->where('service_ticket_id', $ticket->id)->count());
    }

    public function test_customer_reply_closes_open_pause_segments(): void {
        $ticket = $this->ticket(['status' => ServiceTicketStatus::WaitingCustomer->value, 'resolution_due_at' => now()->addHours(4)]);
        SlaClockSegment::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'target' => SlaClockSegment::TARGET_RESOLUTION,
            'paused_from' => now()->subHour(),
            'reason' => 'waiting_customer',
        ]);

        app(TicketConversationService::class)->inbound($ticket, 'Hier die Seriennummer.', 'portal');

        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertSame(0, SlaClockSegment::query()->where('service_ticket_id', $ticket->id)->whereNull('paused_to')->count());
    }

    public function test_reopening_requires_a_reason_and_keeps_history(): void {
        $resolvedAt = now()->subDay()->startOfSecond();
        $ticket = $this->ticket([
            'status' => ServiceTicketStatus::Done->value,
            'resolved_at' => $resolvedAt,
            'close_code' => TicketCloseCode::Solved->value,
        ]);

        $this->actingAs($this->agent)->get(route('service-tickets.status-form', $ticket))
            ->assertOk()
            ->assertSee('name="reopen_reason"', false);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'in_progress'])
            ->assertSessionHasErrors('reopen_reason');
        $this->assertSame(ServiceTicketStatus::Done, $ticket->fresh()->status);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'in_progress', 'reopen_reason' => 'Fehler tritt wieder auf'])
            ->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);
        $this->assertTrue($resolvedAt->equalTo($ticket->resolved_at));
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.reopened', 'auditable_id' => $ticket->id]);
    }

    public function test_status_change_needs_the_ticket_right(): void {
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $ticket = $this->ticket(['status' => ServiceTicketStatus::Done->value, 'resolved_at' => now()]);

        $response = $this->actingAs($viewer)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'closed', 'close_code' => 'solved']);

        $response->assertForbidden();
        $this->assertSame(ServiceTicketStatus::Done, $ticket->fresh()->status);
    }
}
