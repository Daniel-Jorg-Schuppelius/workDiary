<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskIncidentTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\{ServiceTicketPriority, TicketSeverity};
use App\Models\{Organization, ServiceTicket, ServiceTicketLink, ServiceTicketMessage, User};
use App\Services\ServiceTicket\TicketIncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P5 (MVP-155): Impact×Urgency-Matrix (Default + Org-Setting,
 * Override auditiert), Major-Incident-Lifecycle mit system_event-Zeitlinie,
 * Verknüpfungen mit harter Tenant-Grenze und Idempotenz.
 */
final class HelpdeskIncidentTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    private function ticket(array $overrides = []): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'assigned_to_user_id' => $this->agent->id,
            ...$overrides,
        ]);
    }

    public function test_priority_matrix_suggests_and_override_is_audited(): void {
        $service = app(TicketIncidentService::class);
        $ticket = $this->ticket();

        // Default-Matrix: Impact hoch × Urgency hoch → urgent.
        $ticket = $service->classify($ticket, TicketSeverity::High, TicketSeverity::High);
        $this->assertSame(ServiceTicketPriority::Urgent, $ticket->priority);
        $this->assertSame(TicketSeverity::High, $ticket->impact);

        // Override abweichend vom Vorschlag → Audit-Eintrag.
        $ticket = $service->classify($ticket, TicketSeverity::High, TicketSeverity::High, ServiceTicketPriority::Low, $this->agent);
        $this->assertSame(ServiceTicketPriority::Low, $ticket->priority);
        $this->assertTrue(
            \App\Models\AuditLog::query()->where('event', 'service_ticket.priority_overridden')->exists(),
        );

        // Org-Matrix über Setting übersteuert den Default.
        $settings = (array) $this->org->settings;
        $settings['helpdesk'] = ['priority_matrix' => [3 => [3 => 'high']]];
        $this->org->update(['settings' => $settings]);
        app()->instance('currentOrganization', $this->org->fresh());

        $suggested = $service->suggestPriority($this->org->fresh(), TicketSeverity::High, TicketSeverity::High);
        $this->assertSame(ServiceTicketPriority::High, $suggested);
    }

    public function test_major_incident_lifecycle_writes_timeline(): void {
        $service = app(TicketIncidentService::class);
        $ticket = $this->ticket();

        $ticket = $service->markMajor($ticket, $this->agent, ['gf@acme.test'], 'stündlich', $this->agent);
        $this->assertTrue($ticket->is_major);
        $this->assertSame((int) $this->agent->id, (int) $ticket->incident_lead_id);
        $this->assertSame(['gf@acme.test'], $ticket->stakeholders);

        $ticket = $service->unmarkMajor($ticket, $this->agent);
        $this->assertFalse($ticket->is_major);

        // Zeitlinie = Konversation: zwei system_events.
        $this->assertSame(
            2,
            ServiceTicketMessage::query()
                ->where('service_ticket_id', $ticket->id)
                ->where('kind', 'system_event')->count(),
        );
    }

    public function test_links_enforce_tenant_boundary_and_are_idempotent(): void {
        $service = app(TicketIncidentService::class);
        $ticket = $this->ticket();
        $other = $this->ticket(['title' => 'Duplikat']);

        $service->link($ticket, $other, 'duplicate', $this->agent);
        $service->link($ticket, $other, 'duplicate', $this->agent);
        $this->assertSame(1, ServiceTicketLink::query()->count(), 'Idempotent über Unique.');

        // Selbstverknüpfung verboten.
        try {
            $service->link($ticket, $ticket, 'related');
            $this->fail('Selbstverknüpfung wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        // Fremde Org: harte Grenze.
        $foreign = ServiceTicket::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $this->expectException(\RuntimeException::class);
        $service->link($ticket, $foreign, 'related');
    }
}
