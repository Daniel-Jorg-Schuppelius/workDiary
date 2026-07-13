<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : HelpdeskProblemUiTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Helpdesk;

use App\Enums\User\Permission;
use App\Models\{KnowledgeArticleLink, Organization, Problem, ServiceTicket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\WithOrganization;
use Tests\TestCase;

/**
 * Feature 065, MVP-156: Problem-UI — org-gescopte Liste/Detailseite,
 * Modal-CRUD (Anlage auch aus Incidents via openFromIncidents), Transition
 * mit Pflichtfrist beim Lösen (UI + Service), idempotente Known-Error-
 * Veröffentlichung, 403 ohne service_desk.problem.manage, Fremd-Org-404.
 */
final class HelpdeskProblemUiTest extends TestCase {
    use RefreshDatabase;
    use WithOrganization;

    private User $manager;

    protected function setUp(): void {
        parent::setUp();
        $this->setUpOrganization();
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->organization->id);

        $this->manager = User::factory()->teamleitung()->create(['organization_id' => $this->organization->id]);
    }

    /** @param array<string, mixed> $overrides */
    private function problem(array $overrides = []): Problem {
        return Problem::query()->create([
            'organization_id' => $this->organization->id,
            'title' => 'Wiederkehrender Mailausfall',
            'owner_id' => $this->manager->id,
            ...$overrides,
        ]);
    }

    private function incident(string $title = 'Mail down'): ServiceTicket {
        return ServiceTicket::factory()->create([
            'organization_id' => $this->organization->id,
            'title' => $title,
        ]);
    }

    public function test_index_is_org_scoped_and_filters_by_status(): void {
        $this->problem(['title' => 'Eigenes Problem', 'status' => 'analyzing']);
        $this->problem(['title' => 'Geschlossenes Problem', 'status' => 'closed']);
        Problem::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'title' => 'Fremdes Problem',
        ]);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.index'))
            ->assertOk()
            ->assertSee('Eigenes Problem')
            ->assertSee('Geschlossenes Problem')
            ->assertDontSee('Fremdes Problem');

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.index', ['status' => 'analyzing']))
            ->assertOk()
            ->assertSee('Eigenes Problem')
            ->assertDontSee('Geschlossenes Problem');

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.index', ['q' => 'Geschlossenes']))
            ->assertOk()
            ->assertSee('Geschlossenes Problem')
            ->assertDontSee('Eigenes Problem');
    }

    public function test_show_displays_analysis_incidents_and_effectiveness(): void {
        $incident = $this->incident('Postfach nicht erreichbar');
        $problem = $this->problem([
            'root_cause' => 'Defekter Speichercontroller',
            'workaround' => 'Failover-Knoten nutzen',
        ]);
        $problem->tickets()->attach($incident->id);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.show', $problem))
            ->assertOk()
            ->assertSee('Wiederkehrender Mailausfall')
            ->assertSee('Defekter Speichercontroller')
            ->assertSee('Failover-Knoten nutzen')
            ->assertSee('Postfach nicht erreichbar');
    }

    public function test_create_modal_prefills_incident_and_store_links_pivot(): void {
        $incident = $this->incident('Vorbelegter Incident');

        // Modal aus dem Ticket-Detail: Incident-Sqid vorbelegt.
        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.create', ['incidents' => [$incident->sqid]]))
            ->assertOk()
            ->assertSee($incident->ticket_no);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.store'), [
                'title' => 'Problem aus Incident',
                'description' => 'Mehrere gleichartige Störungen.',
                'incidents' => [$incident->sqid],
            ])
            ->assertSessionHasNoErrors();

        $problem = Problem::query()->where('title', 'Problem aus Incident')->firstOrFail();
        $this->assertSame(1, $problem->tickets()->count());
        $this->assertSame((int) $incident->id, (int) $problem->tickets()->first()?->id);
        $this->assertSame((int) $this->manager->id, (int) $problem->owner_id);
    }

    public function test_store_without_incidents_creates_plain_problem(): void {
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.store'), ['title' => 'Freies Problem'])
            ->assertSessionHasNoErrors();

        $problem = Problem::query()->where('title', 'Freies Problem')->firstOrFail();
        $this->assertSame(0, $problem->tickets()->count());
        $this->assertSame('open', $problem->status);
    }

    public function test_store_rejects_foreign_incident(): void {
        $foreign = ServiceTicket::factory()->create([
            'organization_id' => Organization::factory()->create()->id,
        ]);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.store'), [
                'title' => 'Cross-Tenant-Versuch',
                'incidents' => [$foreign->sqid],
            ])
            ->assertSessionHasErrors('incidents');

        $this->assertNull(Problem::query()->where('title', 'Cross-Tenant-Versuch')->first());
    }

    public function test_edit_update_roundtrip(): void {
        $problem = $this->problem();

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.edit', $problem))
            ->assertOk();

        $this->actingAs($this->manager)
            ->patch(route('servicedesk.problems.update', $problem), [
                'title' => 'Mailausfall (analysiert)',
                'root_cause' => 'Firmware-Bug',
                'workaround' => 'Failover nutzen',
                'visibility' => 'customer',
            ])
            ->assertRedirect(route('servicedesk.problems.show', $problem));

        $problem->refresh();
        $this->assertSame('Mailausfall (analysiert)', $problem->title);
        $this->assertSame('Firmware-Bug', $problem->root_cause);
        $this->assertSame('customer', $problem->visibility);
    }

    public function test_transition_requires_effectiveness_due_for_resolved(): void {
        $problem = $this->problem(['status' => 'analyzing']);

        // Lösen ohne Frist: Pflichtfeld über die UI-Validierung.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.transition', $problem), ['status' => 'resolved'])
            ->assertSessionHasErrors('effectiveness_check_due_at');
        $this->assertSame('analyzing', $problem->fresh()->status);

        // Unzulässiger Sprung: die Service-Matrix ist die einzige Wahrheit.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.transition', $problem), ['status' => 'closed'])
            ->assertSessionHasErrors('status');

        // Lösen mit Frist geht durch und setzt die Wiedervorlage.
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.transition', $problem), [
                'status' => 'resolved',
                'effectiveness_check_due_at' => now()->addWeeks(2)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect(route('servicedesk.problems.show', $problem));

        $problem->refresh();
        $this->assertSame('resolved', $problem->status);
        $this->assertNotNull($problem->effectiveness_check_due_at);
    }

    public function test_effectiveness_is_recorded_via_ui(): void {
        $problem = $this->problem([
            'status' => 'resolved',
            'effectiveness_check_due_at' => now()->subDay(),
        ]);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.effectiveness', $problem), [
                'result' => 'Fix greift, keine neuen Incidents.',
            ])
            ->assertRedirect(route('servicedesk.problems.show', $problem));

        $problem->refresh();
        $this->assertNotNull($problem->effectiveness_checked_at);
        $this->assertSame('Fix greift, keine neuen Incidents.', $problem->effectiveness_result);
    }

    public function test_publish_known_error_is_idempotent_via_ui(): void {
        $problem = $this->problem([
            'status' => 'known_error',
            'workaround' => 'Treiber 1.2 verwenden.',
        ]);

        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.publish', $problem))
            ->assertSessionHas('success');
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.publish', $problem))
            ->assertSessionHas('success');

        $this->assertSame(1, KnowledgeArticleLink::query()
            ->where('linkable_type', $problem->getMorphClass())
            ->where('linkable_id', $problem->id)
            ->count());
    }

    public function test_manage_requires_problem_permission(): void {
        $viewer = User::factory()->create(['organization_id' => $this->organization->id]);
        $viewer->givePermissionTo(Permission::ServiceTicketView->value);
        $problem = $this->problem();

        // Sicht folgt dem Ticket-Sichtrecht …
        $this->actingAs($viewer)->get(route('servicedesk.problems.index'))->assertOk();
        $this->actingAs($viewer)->get(route('servicedesk.problems.show', $problem))->assertOk();

        // … Pflege braucht service_desk.problem.manage.
        $this->actingAs($viewer)
            ->post(route('servicedesk.problems.store'), ['title' => 'Verboten'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('servicedesk.problems.transition', $problem), ['status' => 'analyzing'])
            ->assertForbidden();
        $this->actingAs($viewer)
            ->post(route('servicedesk.problems.publish', $problem))
            ->assertForbidden();

        $member = User::factory()->create(['organization_id' => $this->organization->id]);
        $this->actingAs($member)->get(route('servicedesk.problems.index'))->assertForbidden();
    }

    public function test_foreign_org_problem_is_not_reachable(): void {
        $foreign = Problem::query()->create([
            'organization_id' => Organization::factory()->create()->id,
            'title' => 'Fremdes Problem',
        ]);

        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.show', $foreign->sqid))
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->get(route('servicedesk.problems.edit', $foreign->sqid))
            ->assertNotFound();
        $this->actingAs($this->manager)
            ->post(route('servicedesk.problems.transition', $foreign->sqid), ['status' => 'analyzing'])
            ->assertNotFound();
    }
}
