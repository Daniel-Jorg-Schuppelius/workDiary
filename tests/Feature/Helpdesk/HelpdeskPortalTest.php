<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskPortalTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Customer, ServiceQueue, ServiceTicket, ServiceTicketMessage, User};
use App\Services\ServiceTicket\TicketConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, P10 (MVP-160): Portal sieht nur EIGENE Tickets und nur
 * PUBLIC-Inhalte (Notiz-Leak-Test!), Anlage in Portal-Queue mit Source
 * customer_portal, Antwort weckt wartende Tickets, bestätigen/
 * wiedereröffnen, Bewertung genau einmal.
 */
final class HelpdeskPortalTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private Customer $customer;

    private User $portalUser;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->customer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $this->portalUser = User::factory()
            ->kunde((int) $this->customer->id, (int) $this->organization->id)
            ->create(['organization_id' => $this->organization->id]);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    private function ownTicket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'assigned_to_user_id' => $this->agent->id,
            ...$overrides,
        ]);
    }

    public function test_portal_lists_only_own_tickets_and_hides_internal_notes(): void {
        $own = $this->ownTicket(['title' => 'Eigenes Ticket']);
        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $foreignCustomer->id,
            'title' => 'Fremdes Ticket',
        ]);

        app(TicketConversationService::class)->reply($own, $this->agent, 'Öffentliche Antwort ans Portal');
        app(TicketConversationService::class)->note($own, $this->agent, 'GEHEIME interne Notiz');

        $this->actingAs($this->portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\EnforceTwoFactorSetup::class);

        $this->get(route('customer.tickets.index'))
            ->assertOk()
            ->assertSee('Eigenes Ticket')
            ->assertDontSee('Fremdes Ticket');

        // Notiz-Leak-Test: interne Notizen erreichen das Portal NIE.
        $this->get(route('customer.tickets.show', $own))
            ->assertOk()
            ->assertSee('Öffentliche Antwort ans Portal')
            ->assertDontSee('GEHEIME interne Notiz');
    }

    public function test_portal_creates_ticket_in_portal_queue(): void {
        $portalQueue = ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Portal',
            'visibility' => 'portal',
        ]);
        ServiceQueue::query()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Intern',
            'is_default' => true,
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\EnforceTwoFactorSetup::class);

        $this->post(route('customer.tickets.store'), [
            'title' => 'Drucker qualmt',
            'description' => 'Seit heute Morgen.',
        ])->assertRedirect();

        $ticket = ServiceTicket::query()->where('title', 'Drucker qualmt')->firstOrFail();
        $this->assertSame('customer_portal', $ticket->source->value);
        $this->assertSame((int) $portalQueue->id, (int) $ticket->queue_id);
        $this->assertSame((int) $this->customer->id, (int) $ticket->customer_id);
        $this->assertSame((int) $this->portalUser->id, (int) $ticket->requester_id);
    }

    public function test_portal_reply_wakes_waiting_ticket_and_accept_reopen_rate(): void {
        $service = app(\App\Services\ServiceTicket\ServiceTicketService::class);
        $ticket = $this->ownTicket(['status' => ServiceTicketStatus::InProgress]);
        $ticket = $service->wait($ticket, $this->agent, ServiceTicketStatus::WaitingCustomer, 'Info fehlt', now()->addDay());

        $this->actingAs($this->portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\EnforceTwoFactorSetup::class);

        $this->post(route('customer.tickets.reply', $ticket), ['body' => 'Hier die Info'])->assertRedirect();
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);

        // done → bestätigen.
        $ticket->forceFill(['status' => ServiceTicketStatus::Done->value, 'resolved_at' => now()])->save();
        $this->post(route('customer.tickets.accept', $ticket))->assertRedirect();
        $this->assertSame(ServiceTicketStatus::Accepted, $ticket->fresh()->status);

        // Bewertung: genau einmal.
        $this->post(route('customer.tickets.rate', $ticket), ['score' => 5, 'comment' => 'Top'])->assertRedirect();
        $this->post(route('customer.tickets.rate', $ticket), ['score' => 1])->assertRedirect();
        $this->assertSame(1, DB::table('ticket_satisfaction')->count());
        $this->assertSame(5, (int) DB::table('ticket_satisfaction')->value('score'));

        // Wiedereröffnen mit Grund.
        $this->post(route('customer.tickets.reopen', $ticket), ['reason' => 'Fehler tritt wieder auf'])->assertRedirect();
        $this->assertSame(ServiceTicketStatus::InProgress, $ticket->fresh()->status);
        $this->assertTrue(
            ServiceTicketMessage::query()->where('service_ticket_id', $ticket->id)->where('body', 'Fehler tritt wieder auf')->exists(),
        );
    }

    public function test_foreign_ticket_is_not_reachable(): void {
        $foreignCustomer = Customer::factory()->create(['organization_id' => $this->organization->id]);
        $foreign = ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $foreignCustomer->id,
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\EnforceTwoFactorSetup::class);

        $this->get(route('customer.tickets.show', $foreign))->assertNotFound();
        $this->post(route('customer.tickets.reply', $foreign), ['body' => 'Hack'])->assertNotFound();
    }

    /**
     * Known-Error-Leak-Test (MVP-156): das Portal zeigt AUSSCHLIESSLICH
     * Probleme mit status=known_error UND visibility=customer der eigenen
     * Organisation — interne, offene und fremde Probleme erscheinen NIE.
     */
    public function test_known_error_portal_shows_only_customer_visible_known_errors(): void {
        \App\Models\Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Sichtbarer Known Error',
            'workaround' => 'Portal-Workaround: Cache leeren.',
            'status' => 'known_error',
            'visibility' => 'customer',
        ]);
        \App\Models\Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'INTERNER Known Error',
            'status' => 'known_error',
            'visibility' => 'internal',
        ]);
        \App\Models\Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Offenes Kundenproblem',
            'status' => 'open',
            'visibility' => 'customer',
        ]);
        \App\Models\Problem::query()->create([
            'organization_id' => \App\Models\Organization::factory()->create()->id,
            'title' => 'FREMDER Known Error',
            'status' => 'known_error',
            'visibility' => 'customer',
        ]);

        $this->actingAs($this->portalUser, 'customer');
        $this->withoutMiddleware(\App\Http\Middleware\EnforceTwoFactorSetup::class);

        $this->get(route('customer.known-errors.index'))
            ->assertOk()
            ->assertSee('Sichtbarer Known Error')
            ->assertSee('Portal-Workaround: Cache leeren.')
            ->assertDontSee('INTERNER Known Error')
            ->assertDontSee('Offenes Kundenproblem')
            ->assertDontSee('FREMDER Known Error');
    }
}
