<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskProblemTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\ServiceTicket\ServiceTicketStatus;
use App\Models\{KnowledgeArticleLink, Organization, Problem, ServiceTicket, User};
use App\Services\ServiceTicket\{ProblemService, ServiceTicketService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 065, P6 (MVP-156): Übergangsmatrix, Eröffnung aus Incidents,
 * Incident-Schließung schließt Problem NIE, Known-Error → Wissensartikel
 * idempotent, Wirksamkeitsfrist Pflicht beim Lösen.
 */
final class HelpdeskProblemTest extends TestCase {
    use RefreshDatabase;

    private Organization $org;

    private User $agent;

    protected function setUp(): void {
        parent::setUp();
        $this->org = Organization::factory()->create();
        app()->instance('currentOrganization', $this->org);
        $this->agent = User::factory()->teamleitung()->create(['organization_id' => $this->org->id]);
    }

    public function test_open_from_incidents_and_transition_matrix(): void {
        $service = app(ProblemService::class);
        $incidentA = ServiceTicket::factory()->create(['organization_id' => $this->org->id]);
        $incidentB = ServiceTicket::factory()->create(['organization_id' => $this->org->id]);

        $problem = $service->openFromIncidents([$incidentA, $incidentB], 'Wiederkehrender Mailausfall', $this->agent);
        $this->assertSame(2, $problem->tickets()->count());
        $this->assertSame('open', $problem->status);

        // Unzulässiger Sprung open → known_error.
        try {
            $service->transition($problem, 'known_error');
            $this->fail('Unzulässiger Übergang wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        $problem = $service->transition($problem, 'analyzing', $this->agent);
        $problem = $service->transition($problem, 'known_error', $this->agent);

        // resolved braucht Wirksamkeitsfrist.
        try {
            $service->transition($problem, 'resolved');
            $this->fail('Lösen ohne Frist wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }
        $problem = $service->transition($problem, 'resolved', $this->agent, now()->addWeeks(2));
        $this->assertNotNull($problem->effectiveness_check_due_at);

        $problem = $service->recordEffectiveness($problem, $this->agent, 'Fix greift, keine neuen Incidents.');
        $problem = $service->transition($problem, 'closed', $this->agent);
        $this->assertSame('closed', $problem->status);
    }

    public function test_incident_closure_never_closes_problem(): void {
        $incident = ServiceTicket::factory()->create([
            'organization_id' => $this->org->id,
            'status' => ServiceTicketStatus::Done,
            'assigned_to_user_id' => $this->agent->id,
        ]);
        $problem = app(ProblemService::class)->openFromIncidents([$incident], 'Entkoppelt', $this->agent);

        app(ServiceTicketService::class)->transition($incident, $this->agent, ServiceTicketStatus::Closed);

        $this->assertSame('open', $problem->fresh()->status, 'Incident-Schließung koppelt NIE auf das Problem.');
    }

    public function test_known_error_publishes_knowledge_article_idempotently(): void {
        $service = app(ProblemService::class);
        $problem = Problem::query()->create([
            'organization_id' => $this->org->id,
            'title' => 'Druckertreiber-Konflikt',
            'workaround' => 'Treiber 1.2 verwenden.',
            'status' => 'known_error',
        ]);

        $article = $service->publishKnownError($problem, $this->agent);
        $again = $service->publishKnownError($problem, $this->agent);

        $this->assertSame($article->id, $again->id, 'Idempotent über KnowledgeArticleLink.');
        $this->assertSame(1, KnowledgeArticleLink::query()->where('linkable_type', $problem->getMorphClass())->count());
        $this->assertStringContainsString('Druckertreiber-Konflikt', $article->title);
    }

    public function test_cross_org_incident_link_is_blocked(): void {
        $foreign = ServiceTicket::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $local = ServiceTicket::factory()->create(['organization_id' => $this->org->id]);

        $this->expectException(\RuntimeException::class);
        app(ProblemService::class)->openFromIncidents([$local, $foreign], 'Cross-Tenant', $this->agent);
    }
}
