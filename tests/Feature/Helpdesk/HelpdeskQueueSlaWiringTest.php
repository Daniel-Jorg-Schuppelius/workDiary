<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskQueueSlaWiringTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketKind, ServiceTicketStatus};
use App\Models\Customer\Customer;
use App\Models\Platform\{Team, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, ServiceTicketWatcher, SlaContract};
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Services\ServiceTicket\{ServiceTicketService, SlaTimer, TicketConversationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * MVP-1548: Standard-SLA, Ticketarten, Kalender, Absender und Datenführerschaft
 * der Queue wirken; SLA-Vertrag mit Eskalationsstufen und OLA; Vertraulichkeit.
 */
final class HelpdeskQueueSlaWiringTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function queue(array $attributes = []): ServiceQueue {
        return ServiceQueue::query()->create(['organization_id' => $this->organization->id, 'name' => 'Support', ...$attributes]);
    }

    /** @param array<string, mixed> $attributes */
    private function contract(array $attributes = []): SlaContract {
        return SlaContract::factory()->create(['organization_id' => $this->organization->id, 'is_default' => false, ...$attributes]);
    }

    public function test_queue_default_sla_sits_between_customer_and_organization_default(): void {
        $orgDefault = $this->contract(['is_default' => true, 'label' => 'Organisation']);
        $queueContract = $this->contract(['label' => 'Queue']);
        $queue = $this->queue(['is_default' => true, 'default_sla_contract_id' => $queueContract->id]);
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $service = app(ServiceTicketService::class);

        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Drucker']);
        $this->assertSame($queueContract->id, (int) $ticket->sla_contract_id);
        $this->assertSame('Queue', $ticket->sla_snapshot['contract_name']);

        $customerContract = $this->contract(['customer_id' => $customer->id, 'label' => 'Kunde']);
        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Drucker', 'customer_id' => $customer->id]);
        $this->assertSame($customerContract->id, (int) $ticket->sla_contract_id);

        $other = $this->queue(['name' => 'Ohne Vorgabe']);
        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Drucker', 'queue_id' => $other->id]);
        $this->assertSame($orgDefault->id, (int) $ticket->sla_contract_id);
    }

    public function test_ola_contract_is_never_a_customer_sla_but_shows_at_the_team_ticket(): void {
        Carbon::setTestNow('2027-03-01 09:00:00');
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $ola = $this->contract([
            'is_default' => true, 'is_ola' => true, 'ola_team_id' => $team->id, 'label' => 'OLA Technik',
            'priority_table' => ['normal' => ['reaction_minutes' => 30, 'resolution_minutes' => 120]],
        ]);
        $queue = $this->queue(['is_default' => true, 'team_id' => $team->id, 'default_sla_contract_id' => $ola->id]);

        $this->assertNull((new SlaTimer)->resolveContract($this->organization->id, null, null, $queue->id));

        $ticket = app(ServiceTicketService::class)->create($this->organization, $this->agent, ['title' => 'Switch']);
        $this->assertNull($ticket->sla_contract_id);

        $targets = app(SlaTimer::class)->olaTargets($ticket);
        $this->assertNotNull($targets);
        $this->assertSame('2027-03-01 11:00:00', $targets['resolution_due_at']?->format('Y-m-d H:i:s'));

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-ticket-ola', false)
            ->assertSee('OLA Technik');

        Carbon::setTestNow();
    }

    public function test_queue_form_maintains_kinds_calendar_and_sender(): void {
        $this->actingAs($this->agent)
            ->post(route('helpdesk.queues.store'), [
                'name' => 'Störungen',
                'visibility' => 'internal',
                'supported_kinds' => ['incident', 'question'],
                'holiday_region' => 'Germany\\Bavaria',
                'sender_name' => 'Support Musterbetrieb',
                'sender_email' => 'support@example.test',
                'business_hours' => [
                    1 => ['from' => '08:00', 'to' => '17:00'],
                    2 => ['from' => '', 'to' => ''],
                    5 => ['from' => '08:00', 'to' => '14:00'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $queue = ServiceQueue::query()->where('name', 'Störungen')->firstOrFail();
        $this->assertSame(['incident', 'question'], $queue->supported_kinds);
        $this->assertSame('Germany\\Bavaria', $queue->holiday_region);
        $this->assertSame(['name' => 'Support Musterbetrieb', 'email' => 'support@example.test'], $queue->sender_identity);
        $this->assertSame([
            ['weekday' => 1, 'from' => '08:00', 'to' => '17:00'],
            ['weekday' => 5, 'from' => '08:00', 'to' => '14:00'],
        ], $queue->business_hours);

        $this->actingAs($this->agent)->get(route('helpdesk.queues.edit', $queue))
            ->assertOk()
            ->assertSee('value="support@example.test"', false)
            ->assertSee('data-queue-ownership', false);

        $this->actingAs($this->agent)
            ->patch(route('helpdesk.queues.update', $queue), [
                'name' => 'Störungen',
                'visibility' => 'internal',
                'business_hours' => [1 => ['from' => '17:00', 'to' => '08:00']],
            ])
            ->assertSessionHasErrors('business_hours');
    }

    public function test_queue_calendar_drives_deadlines_when_the_contract_has_no_hours(): void {
        // Montag 16:00, Queue arbeitet 08–17 Uhr: 120 Minuten enden Dienstag 09:00.
        Carbon::setTestNow('2027-03-01 16:00:00');
        $contract = $this->contract(['priority_table' => ['normal' => ['reaction_minutes' => 30, 'resolution_minutes' => 120]]]);
        $this->queue([
            'is_default' => true,
            'default_sla_contract_id' => $contract->id,
            'business_hours' => array_map(static fn (int $day): array => ['weekday' => $day, 'from' => '08:00', 'to' => '17:00'], [1, 2, 3, 4, 5]),
        ]);

        $ticket = app(ServiceTicketService::class)->create($this->organization, $this->agent, ['title' => 'Server']);

        $this->assertSame('2027-03-02 09:00:00', $ticket->resolution_due_at?->format('Y-m-d H:i:s'));
        $this->assertNotEmpty($ticket->sla_snapshot['business_hours']);

        Carbon::setTestNow();
    }

    public function test_new_ticket_goes_to_a_queue_that_supports_its_kind(): void {
        $this->queue(['is_default' => true, 'name' => 'Störungen', 'supported_kinds' => ['incident']]);
        $requests = $this->queue(['name' => 'Anfragen', 'supported_kinds' => ['service_request']]);
        $service = app(ServiceTicketService::class);

        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Neues Notebook', 'kind' => ServiceTicketKind::ServiceRequest->value]);
        $this->assertSame($requests->id, (int) $ticket->queue_id);

        $incident = $service->create($this->organization, $this->agent, ['title' => 'Ausfall']);
        $this->expectException(\RuntimeException::class);
        $service->moveToQueue($incident, $this->agent, $requests);
    }

    public function test_externally_led_queue_locks_status_and_replies_and_resets_on_switch_back(): void {
        $queue = $this->queue(['is_default' => true]);
        $connection = ZammadConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Zammad',
            'base_url' => 'https://zammad.example.com',
            'api_token' => 'secret',
            'active' => true,
        ]);
        $importer = app(ZammadTicketImporter::class);
        $connection = $importer->switchTicketTarget($connection, 'service_ticket', $queue, $this->agent);
        $this->assertTrue($queue->fresh()->isExternallyLed());

        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'queue_id' => $queue->id,
            'assigned_to_user_id' => $this->agent->id,
        ]);

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-ticket-external', false)
            ->assertDontSee(route('service-tickets.status-form', $ticket), false);
        $this->actingAs($this->agent)->get(route('service-tickets.status-form', $ticket))->assertStatus(409);
        $this->actingAs($this->agent)
            ->post(route('service-tickets.transition', $ticket), ['status' => 'triaged'])
            ->assertSessionHas('error');
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.reply', $ticket), ['body' => 'Wir kümmern uns.'])
            ->assertSessionHas('error');
        $this->assertSame(ServiceTicketStatus::Reported, $ticket->fresh()->status);
        $this->assertSame(0, \App\Models\ServiceTicket\ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->count());

        $this->actingAs($this->agent)->get(route('helpdesk.queues.index'))
            ->assertOk()
            ->assertSee(__('helpdesk.queue.ownershipExternal'));

        $importer->switchTicketTarget($connection, 'task', null, $this->agent);
        $this->assertFalse($queue->fresh()->isExternallyLed());
        $this->actingAs($this->agent)->get(route('service-tickets.status-form', $ticket))->assertOk();
    }

    public function test_sla_contract_form_maintains_escalation_levels_and_ola_team(): void {
        $team = Team::factory()->create(['organization_id' => $this->organization->id]);
        $lead = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'OLA-NET',
                'label' => 'OLA Netzwerk',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480}}',
                'is_ola' => '1',
                'ola_team_id' => $team->sqid,
                'escalation' => [
                    ['after_minutes' => '120', 'notify' => 'user:' . $lead->sqid],
                    ['after_minutes' => '30', 'notify' => ''],
                    ['after_minutes' => '', 'notify' => ''],
                    ['after_minutes' => '240', 'notify' => 'role:teamleitung'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $contract = SlaContract::query()->where('code', 'OLA-NET')->firstOrFail();
        $this->assertTrue($contract->is_ola);
        $this->assertSame($team->id, (int) $contract->ola_team_id);
        $this->assertSame([
            ['after_minutes' => 30, 'notify' => ''],
            ['after_minutes' => 120, 'notify' => (string) $lead->id],
            ['after_minutes' => 240, 'notify' => 'teamleitung'],
        ], $contract->escalation_chain);

        $this->actingAs($this->agent)->get(route('sla-contracts.show', $contract))
            ->assertOk()
            ->assertSee($lead->name);
        $this->actingAs($this->agent)->get(route('sla-contracts.edit', $contract))
            ->assertOk()
            ->assertSee('name="escalation[0][after_minutes]"', false)
            ->assertSee('name="ola_team_id"', false);

        // OLA ohne Team und OLA als Standardvertrag werden abgewiesen.
        $this->actingAs($this->agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'OLA-X', 'label' => 'OLA ohne Team', 'is_ola' => '1',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480}}',
            ])
            ->assertSessionHasErrors('ola_team_id');
        $this->actingAs($this->agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'OLA-Y', 'label' => 'OLA als Standard', 'is_ola' => '1', 'is_default' => '1', 'ola_team_id' => $team->sqid,
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480}}',
            ])
            ->assertSessionHasErrors('is_ola');
    }

    public function test_confidential_ticket_is_settable_and_hidden_from_other_lists(): void {
        $this->queue(['is_default' => true]);
        $colleague = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.store'), ['title' => 'Personalsache Muster', 'priority' => 'normal', 'confidentiality' => 'restricted'])
            ->assertSessionHasNoErrors();

        $ticket = ServiceTicket::query()->where('title', 'Personalsache Muster')->firstOrFail();
        $this->assertSame('restricted', $ticket->confidentiality);
        $this->assertTrue(ServiceTicketWatcher::query()->where('service_ticket_id', $ticket->id)->where('user_id', $this->agent->id)->exists());

        $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket))->assertOk()->assertSee('data-ticket-confidential', false);
        $this->actingAs($this->agent)->get(route('service-tickets.index'))->assertSee('Personalsache Muster');
        $this->actingAs($colleague)->get(route('service-tickets.index'))->assertOk()->assertDontSee('Personalsache Muster');
        $this->actingAs($colleague)->get(route('helpdesk.board.index'))->assertOk()->assertDontSee('Personalsache Muster');
        $this->actingAs($colleague)->get(route('service-tickets.show', $ticket))->assertForbidden();

        $this->actingAs($this->agent)
            ->post(route('service-tickets.classify', $ticket), ['impact' => 2, 'urgency' => 2, 'confidentiality' => 'normal'])
            ->assertSessionHasNoErrors();
        $this->assertSame('normal', $ticket->fresh()->confidentiality);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.confidentiality_changed', 'auditable_id' => $ticket->id]);
        $this->actingAs($colleague)->get(route('service-tickets.show', $ticket))->assertOk();
    }

    public function test_reply_mail_carries_the_sender_identity_of_the_queue(): void {
        $queue = $this->queue(['sender_identity' => ['name' => 'Support Musterbetrieb', 'email' => 'support@example.test']]);
        $ticket = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'queue_id' => $queue->id]);
        $sent = null;
        Event::listen(MessageSending::class, function (MessageSending $event) use (&$sent): void {
            $sent = $event->message;
        });

        // Die Antwort stößt den Versand selbst an (Queue läuft im Test synchron).
        app(TicketConversationService::class)->reply($ticket, $this->agent, 'Antwort', ['kunde@example.test']);

        $this->assertNotNull($sent);
        $this->assertSame('Support Musterbetrieb', $sent->getFrom()[0]->getName());
        $this->assertSame('support@example.test', $sent->getReplyTo()[0]->getAddress());
    }
}
