<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskFollowUpWiringTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Feature\Helpdesk;

use App\Enums\Notification\{NotificationChannel, NotificationEvent};
use App\Enums\ServiceTicket\{ServiceTicketStatus, TicketCloseCode};
use App\Models\Customer\Customer;
use App\Models\Mail\EmailConnection;
use App\Models\Notification\NotificationRule;
use App\Models\Platform\{Team, User};
use App\Models\ServiceTicket\{ServiceQueue, ServiceTicket, SlaClockSegment, SlaContract};
use App\Plugins\Zammad\Contracts\ZammadGateway;
use App\Plugins\Zammad\Models\ZammadConnection;
use App\Plugins\Zammad\Services\ZammadTicketImporter;
use App\Services\ServiceTicket\ServiceTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\{WithOrganization, WithPortalVisibility};
use Tests\TestCase;

/**
 * Nachzug zu MVP-1546–1549: Portal über den Fachdienst, SLA beim Queue-Wechsel
 * von Hand, vertrauliche Tickets in Verweislisten, Eingangspostfach der Queue,
 * Eskalationskette bis zur Benachrichtigung, Feiertagsregion, Zammad-Abschluss,
 * OLA-Überschreitung.
 */
final class HelpdeskFollowUpWiringTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;
    use WithPortalVisibility;

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

    /** @return array{0: Customer, 1: User} */
    private function portal(): array {
        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->allowPortal($customer);
        $portalUser = User::factory()->kunde((int) $customer->id, (int) $this->organization->id)->create(['organization_id' => $this->organization->id]);
        $this->withoutMiddleware(\App\Http\Middleware\RequireTwoFactorSetup::class);

        return [$customer, $portalUser];
    }

    public function test_portal_acceptance_and_reopening_run_through_the_service(): void {
        [$customer, $portalUser] = $this->portal();
        $resolvedAt = now()->subHour()->startOfSecond();
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'status' => ServiceTicketStatus::Done->value,
            'resolved_at' => $resolvedAt,
            'close_code' => TicketCloseCode::Solved->value,
        ]);

        $this->actingAs($portalUser, 'customer')->post(route('customer.tickets.accept', $ticket))->assertSessionHasNoErrors();
        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::Accepted, $ticket->status);
        $this->assertNotNull($ticket->accepted_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.status_changed', 'auditable_id' => $ticket->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.accepted_by_customer', 'auditable_id' => $ticket->id]);

        // Ohne Bearbeiter: die Wiederöffnung des Kunden scheitert nicht an der internen Zuweisungsregel.
        $this->actingAs($portalUser, 'customer')
            ->post(route('customer.tickets.reopen', $ticket), ['reason' => 'Fehler ist wieder da'])
            ->assertSessionHasNoErrors();
        $ticket->refresh();
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->status);
        $this->assertTrue($resolvedAt->equalTo($ticket->resolved_at));
        $this->assertNotNull($ticket->started_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.reopened', 'auditable_id' => $ticket->id]);
    }

    public function test_portal_is_locked_for_an_externally_led_queue(): void {
        [$customer, $portalUser] = $this->portal();
        $queue = $this->queue(['data_ownership' => ServiceQueue::OWNERSHIP_EXTERNAL]);
        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::Done->value,
            'resolved_at' => now(),
        ]);

        $this->actingAs($portalUser, 'customer')->get(route('customer.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-portal-ticket-external', false)
            ->assertDontSee(route('customer.tickets.reply', $ticket), false)
            ->assertDontSee(route('customer.tickets.accept', $ticket), false);

        foreach ([
            [route('customer.tickets.accept', $ticket), []],
            [route('customer.tickets.reopen', $ticket), ['reason' => 'Fehler ist wieder da']],
            [route('customer.tickets.reply', $ticket), ['body' => 'Hallo']],
        ] as [$url, $payload]) {
            $this->actingAs($portalUser, 'customer')->post($url, $payload)->assertSessionHas('error');
        }
        $this->assertSame(ServiceTicketStatus::Done, $ticket->fresh()->status);
        $this->assertSame(0, \App\Models\ServiceTicket\ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->count());
    }

    public function test_manual_queue_change_follows_the_queue_default_but_keeps_customer_and_explicit_contracts(): void {
        $first = $this->contract(['label' => 'Queue A']);
        $second = $this->contract(['label' => 'Queue B', 'priority_table' => ['normal' => ['reaction_minutes' => 10, 'resolution_minutes' => 60]]]);
        $queueA = $this->queue(['name' => 'A', 'is_default' => true, 'default_sla_contract_id' => $first->id]);
        $queueB = $this->queue(['name' => 'B', 'default_sla_contract_id' => $second->id]);
        $service = app(ServiceTicketService::class);

        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Ohne Zusage']);
        $this->assertSame($first->id, (int) $ticket->sla_contract_id);
        $moved = $service->moveToQueue($ticket, $this->agent, $queueB);
        $this->assertSame($second->id, (int) $moved->sla_contract_id);
        $this->assertSame('Queue B', $moved->sla_snapshot['contract_name']);
        $this->assertTrue($moved->reported_at->copy()->addMinutes(60)->equalTo($moved->resolution_due_at));

        $customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $promise = $this->contract(['customer_id' => $customer->id, 'label' => 'Kunde']);
        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Mit Kundenzusage', 'customer_id' => $customer->id]);
        $this->assertSame($promise->id, (int) $service->moveToQueue($ticket, $this->agent, $queueB)->sla_contract_id);

        $ticket = $service->create($this->organization, $this->agent, ['title' => 'Ausdrücklich', 'sla_contract_id' => $first->id]);
        $this->assertSame($first->id, (int) $service->moveToQueue($ticket, $this->agent, $queueB)->sla_contract_id);

        // Bestandsticket ohne Vertrag bekommt die Vorgabe der Ziel-Queue.
        $legacy = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'queue_id' => $queueA->id])->refresh();
        $this->assertSame($second->id, (int) $service->moveToQueue($legacy, $this->agent, $queueB)->sla_contract_id);
    }

    public function test_confidential_ticket_is_masked_in_drilldown_and_hidden_in_pick_lists(): void {
        $colleague = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
        $restricted = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Personalsache Muster',
            'ticket_no' => 'ST-GEHEIM-1',
            'confidentiality' => ServiceTicket::CONFIDENTIALITY_RESTRICTED,
            'assigned_to_user_id' => $this->agent->id,
            'reported_at' => now()->subDays(10),
        ]);
        $other = ServiceTicket::factory()->create(['organization_id' => $this->organization->id, 'assigned_to_user_id' => $colleague->id]);

        $url = \Illuminate\Support\Facades\URL::signedRoute('helpdesk.reports.drilldown', ['kind' => 'aging_band', 'key' => '7-30', 'expected' => 1]);
        $this->actingAs($colleague)->get($url)
            ->assertOk()
            ->assertDontSee('Personalsache Muster')
            ->assertDontSee('ST-GEHEIM-1')
            ->assertSee(__('helpdesk.confidentiality.masked'));
        $this->actingAs($this->agent)->get($url)->assertOk()->assertSee('Personalsache Muster');

        $this->actingAs($colleague)->get(route('helpdesk.tickets.links.create', $other))
            ->assertOk()
            ->assertDontSee('Personalsache Muster');
    }

    public function test_queue_form_maintains_the_inbound_mailbox_once(): void {
        $mailbox = EmailConnection::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Support-Postfach',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'support@example.com',
            'password' => 'secret',
            'folder' => 'INBOX',
            'active' => true,
        ]);

        $this->actingAs($this->agent)->get(route('helpdesk.queues.create'))->assertOk()->assertSee('Support-Postfach');
        $this->actingAs($this->agent)
            ->post(route('helpdesk.queues.store'), ['name' => 'Mail-Queue', 'visibility' => 'internal', 'email_connection_id' => $mailbox->sqid])
            ->assertSessionHasNoErrors();
        $this->assertSame($mailbox->id, (int) ServiceQueue::query()->where('name', 'Mail-Queue')->value('email_connection_id'));

        $this->actingAs($this->agent)
            ->post(route('helpdesk.queues.store'), ['name' => 'Zweite', 'visibility' => 'internal', 'email_connection_id' => $mailbox->sqid])
            ->assertSessionHasErrors('email_connection_id');
    }

    public function test_escalation_level_from_the_form_notifies_its_recipient(): void {
        Carbon::setTestNow('2027-03-01 12:00:00');
        NotificationRule::factory()->forEvent(NotificationEvent::SlaBreached)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'recipient_roles' => [],
        ]);
        $lead = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('sla-contracts.store'), [
                'code' => 'SLA-ESK',
                'label' => 'Mit Eskalation',
                'priority_table' => '{"normal": {"reaction_minutes": 60, "resolution_minutes": 480}}',
                'escalation' => [['after_minutes' => '30', 'notify' => 'user:' . $lead->sqid], ['after_minutes' => '600', 'notify' => '']],
            ])
            ->assertSessionHasNoErrors();
        $contract = SlaContract::query()->where('code', 'SLA-ESK')->firstOrFail();

        $ticket = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'sla_contract_id' => $contract->id,
            'status' => ServiceTicketStatus::InProgress->value,
            'reported_at' => '2027-03-01 02:00:00',
            'resolution_due_at' => '2027-03-01 11:15:00',
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        // 45 Minuten über der Frist: Stufe 1 (30 Min.) ist erreicht, Stufe 2 (600 Min.) nicht.
        $this->assertSame(1, $lead->notifications()->count());
        $this->assertSame(1, (int) $ticket->fresh()->escalation_level);

        Carbon::setTestNow();
    }

    public function test_regional_holiday_of_the_queue_moves_the_deadline(): void {
        // Di 05.01.2027 16:00, 120 Minuten bei 08–17 Uhr. Der 06.01. ist nur im Rechtsraum der Queue Feiertag.
        Carbon::setTestNow('2027-01-05 16:00:00');
        $contract = $this->contract(['priority_table' => ['normal' => ['reaction_minutes' => 30, 'resolution_minutes' => 120]]]);
        $hours = array_map(static fn (int $day): array => ['weekday' => $day, 'from' => '08:00', 'to' => '17:00'], [1, 2, 3, 4, 5]);
        $regional = $this->queue(['name' => 'Süd', 'default_sla_contract_id' => $contract->id, 'business_hours' => $hours, 'holiday_region' => 'Germany\\Bavaria']);
        $federal = $this->queue(['name' => 'Bund', 'default_sla_contract_id' => $contract->id, 'business_hours' => $hours]);
        $service = app(ServiceTicketService::class);

        $south = $service->create($this->organization, $this->agent, ['title' => 'Süd', 'queue_id' => $regional->id]);
        $north = $service->create($this->organization, $this->agent, ['title' => 'Bund', 'queue_id' => $federal->id]);

        $this->assertSame('2027-01-07 09:00:00', $south->resolution_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-06 09:00:00', $north->resolution_due_at?->format('Y-m-d H:i:s'));
        $this->assertSame('Germany\\Bavaria', $south->sla_snapshot['holiday_region']);

        Carbon::setTestNow();
    }

    public function test_zammad_closing_closes_the_service_ticket_of_an_externally_led_queue(): void {
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
        $row = ['id' => 71, 'number' => '81071', 'title' => 'Mail down', 'state' => 'open', 'group_id' => null];

        $importer->import($connection, $this->gateway([$row]), $this->agent);
        $ticket = ServiceTicket::query()->where('source_reference', 'zammad:71')->firstOrFail();
        $this->assertFalse($ticket->status->isTerminal());

        $result = $importer->import($connection, $this->gateway([['state' => 'closed'] + $row]), $this->agent);

        $ticket->refresh();
        $this->assertSame(1, $result['updated']);
        $this->assertSame(ServiceTicketStatus::Closed, $ticket->status);
        $this->assertSame(TicketCloseCode::Other, $ticket->close_code);
        $this->assertNotNull($ticket->closed_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'service_ticket.closed', 'auditable_id' => $ticket->id]);
    }

    public function test_exceeded_ola_target_notifies_the_team_lead_once(): void {
        Carbon::setTestNow('2027-03-01 12:00:00');
        NotificationRule::factory()->forEvent(NotificationEvent::SlaOlaBreached)->create([
            'organization_id' => $this->organization->id,
            'channels' => [NotificationChannel::InApp->value],
            'recipient_roles' => [],
        ]);
        $lead = User::factory()->create(['organization_id' => $this->organization->id]);
        $team = Team::factory()->create(['organization_id' => $this->organization->id, 'lead_user_id' => $lead->id]);
        $this->contract([
            'is_ola' => true, 'ola_team_id' => $team->id,
            'priority_table' => ['normal' => ['reaction_minutes' => 30, 'resolution_minutes' => 120]],
        ]);
        $queue = $this->queue(['team_id' => $team->id]);
        $late = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id, 'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::InProgress->value, 'acknowledged_at' => '2027-03-01 08:10:00',
            'reported_at' => '2027-03-01 08:00:00',
        ]);
        ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id, 'queue_id' => $queue->id,
            'status' => ServiceTicketStatus::InProgress->value, 'acknowledged_at' => '2027-03-01 11:10:00',
            'reported_at' => '2027-03-01 11:00:00',
        ]);

        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);
        $this->artisan('notifications:scan-deadlines')->assertExitCode(0);

        $this->assertSame(1, $lead->notifications()->count());
        $data = (array) $lead->notifications()->first()?->data;
        $this->assertSame(NotificationEvent::SlaOlaBreached->value, $data['event'] ?? null);
        $this->assertStringContainsString($late->ticket_no, (string) ($data['title'] ?? ''));
        $this->assertSame(0, SlaClockSegment::query()->count());

        Carbon::setTestNow();
    }

    /** @param array<int, array<string, mixed>> $tickets */
    private function gateway(array $tickets): ZammadGateway {
        return new class($tickets) implements ZammadGateway {
            /** @param array<int, array<string, mixed>> $tickets */
            public function __construct(private array $tickets) {}

            public function listTickets(?int $groupId = null, int $page = 1, int $perPage = 100): array {
                return $this->tickets;
            }

            public function probe(): void {}

            public function updateTicketState(int $ticketId, ?string $state, ?string $note): bool {
                return true;
            }

            public function accountTime(int $ticketId, float $timeUnit): bool {
                return true;
            }
        };
    }
}
