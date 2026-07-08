<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintReportTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileSprintService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature 064, P8 (MVP-146): Sprint-Cockpit rendert Burndown/Burnup/
 * Velocity/Qualität aus Events/Snapshots; A11y-Pflicht (Tabelle unter jedem
 * Diagramm, in der Komponente erzwungen); Leerzustand ohne gestarteten
 * Sprint; fremdes Projekt 404 (Org-Scope).
 */
final class AgileSprintReportTest extends TestCase {
    use RefreshDatabase;

    private \App\Models\Agile\AgileBoard $board;

    private User $lead;

    private Project $project;

    protected function setUp(): void {
        parent::setUp();
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $org->id]);
        $this->project = Project::factory()->create(['organization_id' => $org->id]);
        $this->board = app(AgileBoardService::class)->activate($this->project, \App\Models\Agile\AgileBoard::METHOD_SCRUM, $this->lead);
    }

    protected function tearDown(): void {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cockpit_shows_empty_state_without_started_sprint(): void {
        $this->actingAs($this->lead)->get(route('agile.reports.sprint', $this->project))
            ->assertOk()
            ->assertSee(__('Noch kein gestarteter Sprint vorhanden.'));
    }

    public function test_cockpit_renders_charts_with_equivalent_tables(): void {
        $items = app(AgileWorkItemService::class);
        $sprints = app(AgileSprintService::class);
        $boards = app(AgileBoardService::class);

        Carbon::setTestNow('2026-07-01 09:00:00');
        $a = $items->create($this->board, ['title' => 'Cockpit-Story', 'story_points' => 5], $this->lead);
        $sprint = $sprints->plan($this->board, [
            'name' => 'Sprint C1', 'goal' => 'Cockpit',
            'starts_on' => '2026-07-01', 'ends_on' => '2026-07-10',
        ], $this->lead);
        $sprints->assign($sprint, $a, $this->lead);
        $sprint = $sprints->start($sprint, $this->lead);

        Carbon::setTestNow('2026-07-02 09:00:00');
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        $boards->move($a->fresh(), $done, (int) $a->fresh()->lock_version, null, $this->lead);

        Carbon::setTestNow('2026-07-03 09:00:00');
        $sprints->complete($sprint->fresh(), [], $this->lead);
        Carbon::setTestNow();

        $response = $this->actingAs($this->lead)
            ->get(route('agile.reports.sprint', [$this->project, 'sprint' => $sprint->fresh()->sqid]))
            ->assertOk()
            ->assertSee(__('Sprint-Cockpit'))
            ->assertSee('Burndown')
            ->assertSee('Velocity')
            ->assertSee(__('Sprintabschlussbericht — :name (unveränderlich)', ['name' => 'Sprint C1']));

        // A11y: je Diagramm eine gleichwertige Tabelle + fokussierbare Punkte.
        $html = $response->getContent();
        $this->assertGreaterThanOrEqual(2, substr_count($html, '<figure'));
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('aria-label', $html);
    }

    public function test_foreign_project_is_not_reachable(): void {
        $otherOrg = Organization::factory()->create();
        $foreign = Project::factory()->create(['organization_id' => $otherOrg->id]);

        $this->actingAs($this->lead)
            ->get(route('agile.reports.sprint', $foreign))
            ->assertNotFound();
    }
}
