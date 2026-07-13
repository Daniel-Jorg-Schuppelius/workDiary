<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskTicketWidgetsTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{Organization, ServiceTicket, ServiceTicketLink, ServiceTicketWatcher, SlaClockSegment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-160: Ticket-Detail-Widgets — Beobachter idempotent mit
 * Org-404, Verknüpfungs-Sqid strikt je Zielklasse + Tenant-Grenze,
 * Major-Incident-Lifecycle erzeugt system_events, SLA-Uhr zeigt offene
 * Pausen; Zuweisung läuft über User-Sqid (kein rohes ID-Input mehr).
 */
final class HelpdeskTicketWidgetsTest extends TestCase {
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
            ...$overrides,
        ]);
    }

    public function test_watcher_store_is_idempotent_and_org_scoped(): void {
        $ticket = $this->ticket();
        $watcher = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.watchers.store', $ticket), ['user' => $watcher->sqid])
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.watchers.store', $ticket), ['user' => $watcher->sqid])
            ->assertRedirect(route('service-tickets.show', $ticket));

        $this->assertSame(1, ServiceTicketWatcher::query()->where('service_ticket_id', $ticket->id)->count());

        // Fremde Organisation: harte 404, kein Datensatz.
        $foreignUser = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.watchers.store', $ticket), ['user' => $foreignUser->sqid])
            ->assertNotFound();
        $this->assertSame(1, ServiceTicketWatcher::query()->where('service_ticket_id', $ticket->id)->count());

        // Entfernen ist ebenfalls idempotent (zweiter Aufruf bleibt Redirect).
        $this->actingAs($this->agent)
            ->delete(route('helpdesk.tickets.watchers.destroy', [$ticket, $watcher]))
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertSame(0, ServiceTicketWatcher::query()->where('service_ticket_id', $ticket->id)->count());
        $this->actingAs($this->agent)
            ->delete(route('helpdesk.tickets.watchers.destroy', [$ticket, $watcher]))
            ->assertRedirect(route('service-tickets.show', $ticket));
    }

    public function test_link_store_decodes_sqid_per_target_class_and_enforces_tenant_boundary(): void {
        $ticket = $this->ticket();
        $target = $this->ticket(['title' => 'Zielticket']);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.links.store', $ticket), [
                'kind' => 'duplicate',
                'target' => $target->sqid,
            ])
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertSame(1, ServiceTicketLink::query()->where('service_ticket_id', $ticket->id)->count());

        // Idempotent über den Unique-Index (Service: firstOrCreate).
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.links.store', $ticket), [
                'kind' => 'duplicate',
                'target' => $target->sqid,
            ])
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertSame(1, ServiceTicketLink::query()->where('service_ticket_id', $ticket->id)->count());

        // Sqid einer FALSCHEN Zielklasse (User) dekodiert nicht als Ticket → 404.
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.links.store', $ticket), [
                'kind' => 'related',
                'target' => $this->agent->sqid,
            ])
            ->assertNotFound();

        // Tenant-Grenze: fremdes Ticket → 404, kein Link.
        $foreign = ServiceTicket::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.links.store', $ticket), [
                'kind' => 'related',
                'target' => $foreign->sqid,
            ])
            ->assertNotFound();
        $this->assertSame(1, ServiceTicketLink::query()->where('service_ticket_id', $ticket->id)->count());

        // Unbekannte Art wird abgelehnt (Dialog kennt nur Ticket-Arten).
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.links.store', $ticket), [
                'kind' => 'security',
                'target' => $target->sqid,
            ])
            ->assertSessionHasErrors('kind');
    }

    public function test_major_incident_lifecycle_writes_system_events(): void {
        $ticket = $this->ticket();
        $lead = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.major.store', $ticket), [
                'incident_lead' => $lead->sqid,
                'stakeholders' => 'gf@acme.test, ops@acme.test',
                'comm_rhythm' => 'stündlich',
            ])
            ->assertRedirect(route('service-tickets.show', $ticket));

        $ticket->refresh();
        $this->assertTrue($ticket->is_major);
        $this->assertSame((int) $lead->id, (int) $ticket->incident_lead_id);
        $this->assertSame(['gf@acme.test', 'ops@acme.test'], $ticket->stakeholders);
        $this->assertSame('stündlich', $ticket->comm_rhythm);

        // Fremder Lead: Validierungsfehler, Status bleibt.
        $foreignLead = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->agent)
            ->post(route('helpdesk.tickets.major.store', $ticket), ['incident_lead' => $foreignLead->sqid])
            ->assertSessionHasErrors('incident_lead');

        $this->actingAs($this->agent)
            ->delete(route('helpdesk.tickets.major.destroy', $ticket))
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertFalse($ticket->refresh()->is_major);

        // Zeitlinie = Konversation: Beginn + Ende als system_event.
        $this->assertSame(
            2,
            \App\Models\ServiceTicketMessage::query()
                ->where('service_ticket_id', $ticket->id)
                ->where('kind', 'system_event')
                ->count(),
        );
    }

    public function test_sla_clock_widget_shows_open_pause_and_wait_fields(): void {
        $ticket = $this->ticket([
            'status' => ServiceTicketStatus::WaitingCustomer,
            'reaction_due_at' => now()->addHours(2),
            'resolution_due_at' => now()->addHours(8),
            'wait_reason' => 'Rückfrage-beim-Kunden',
            'wait_until' => now()->addDay(),
            'wait_owner_id' => $this->agent->id,
        ]);
        SlaClockSegment::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'target' => SlaClockSegment::TARGET_RESOLUTION,
            'paused_from' => now()->subHour(),
            'reason' => ServiceTicketStatus::WaitingCustomer->value,
        ]);

        $response = $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('SLA-Uhr');
        $response->assertSee('SLA-Uhr pausiert');
        $response->assertSee('Rückfrage-beim-Kunden');
        $response->assertSee('Wartegrund');
        $response->assertSee($this->agent->name);
    }

    public function test_assign_uses_user_sqid_and_rejects_foreign_users(): void {
        $ticket = $this->ticket(['assigned_to_user_id' => null]);
        $assignee = User::factory()->create(['organization_id' => $this->organization->id]);

        $this->actingAs($this->agent)
            ->post(route('service-tickets.assign', $ticket), ['assignee_user_id' => $assignee->sqid])
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertSame((int) $assignee->id, (int) $ticket->fresh()->assigned_to_user_id);

        // Fremde Organisation: Fehler, Zuweisung bleibt unverändert.
        $foreign = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->actingAs($this->agent)
            ->post(route('service-tickets.assign', $ticket), ['assignee_user_id' => $foreign->sqid])
            ->assertSessionHasErrors('assignee_user_id');
        $this->assertSame((int) $assignee->id, (int) $ticket->fresh()->assigned_to_user_id);

        // Leeres Feld hebt die Zuweisung auf.
        $this->actingAs($this->agent)
            ->post(route('service-tickets.assign', $ticket), ['assignee_user_id' => ''])
            ->assertRedirect(route('service-tickets.show', $ticket));
        $this->assertNull($ticket->fresh()->assigned_to_user_id);
    }

    public function test_widgets_render_on_show_page(): void {
        $other = $this->ticket(['title' => 'Verknüpftes-Ziel']);
        $ticket = $this->ticket();
        $watcher = User::factory()->create(['organization_id' => $this->organization->id]);
        ServiceTicketWatcher::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'user_id' => $watcher->id,
        ]);
        ServiceTicketLink::query()->create([
            'organization_id' => $this->organization->id,
            'service_ticket_id' => $ticket->id,
            'linked_type' => $other->getMorphClass(),
            'linked_id' => $other->id,
            'kind' => 'related',
        ]);

        $response = $this->actingAs($this->agent)->get(route('service-tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Beobachter');
        $response->assertSee($watcher->name);
        $response->assertSee('Verknüpfungen');
        // linked_type erscheint NIE als roher Morph-Klassenname.
        $response->assertDontSee('App\\Models\\ServiceTicket');
        $response->assertSee('Verknüpftes-Ziel');
        $response->assertSee('Major Incident');
        // Zuweisen-Formular nutzt die Sqid, nicht die rohe User-ID.
        $response->assertSee($this->agent->sqid);
    }

    public function test_link_dialog_lists_org_tickets_only(): void {
        $ticket = $this->ticket();
        $own = $this->ticket(['title' => 'Eigenes-Ziel']);
        ServiceTicket::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
            'title' => 'Fremdes-Ziel',
        ]);

        $response = $this->actingAs($this->agent)->get(route('helpdesk.tickets.links.create', $ticket));

        $response->assertOk();
        $response->assertSee('Eigenes-Ziel');
        $response->assertSee($own->sqid);
        $response->assertDontSee('Fremdes-Ziel');
    }
}
