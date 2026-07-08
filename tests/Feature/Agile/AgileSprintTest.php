<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\{AgileEvent, AgileSprint, AgileWorkItem};
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileSprintService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 064, P4 (MVP-142): Startregeln (Ziel/Zeitraum/≥1 Element, genau
 * EIN aktiver Sprint je Board), Commitment-Unveränderlichkeit, Abschluss-
 * Zwangsentscheidung mit Carry-over, Scope-Events nach Start,
 * Methodenwechsel-Sperre bei aktivem Sprint, kein Wiederöffnen.
 */
final class AgileSprintTest extends TestCase {
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

    private function item(string $title = 'Story', ?int $points = null): AgileWorkItem {
        return app(AgileWorkItemService::class)->create($this->board, [
            'title' => $title,
            'story_points' => $points,
        ], $this->lead);
    }

    /** @param array<string, mixed> $overrides */
    private function plannedSprint(array $overrides = []): AgileSprint {
        return app(AgileSprintService::class)->plan($this->board, [
            'name' => 'Sprint 1',
            'goal' => 'Lieferfähig',
            'starts_on' => '2026-07-06',
            'ends_on' => '2026-07-17',
            ...$overrides,
        ], $this->lead);
    }

    public function test_start_rules_are_enforced(): void {
        $service = app(AgileSprintService::class);

        // Ohne Element.
        $sprint = $this->plannedSprint();
        try {
            $service->start($sprint, $this->lead);
            $this->fail('Start ohne Element wurde akzeptiert.');
        } catch (\RuntimeException) {
        }
        $this->assertSame(AgileSprint::STATUS_PLANNED, $sprint->fresh()->status);

        // Ohne Ziel.
        $empty = $this->plannedSprint(['name' => 'Sprint 2', 'goal' => null]);
        $service->assign($empty, $this->item(), $this->lead);
        try {
            $service->start($empty, $this->lead);
            $this->fail('Start ohne Ziel wurde akzeptiert.');
        } catch (\RuntimeException) {
        }

        // Ungültiger Zeitraum.
        $invalid = $this->plannedSprint(['name' => 'Sprint 3', 'starts_on' => '2026-07-17', 'ends_on' => '2026-07-06']);
        $service->assign($invalid, $this->item('B'), $this->lead);
        try {
            $service->start($invalid, $this->lead);
            $this->fail('Start mit ungültigem Zeitraum wurde akzeptiert.');
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, AgileSprint::query()->where('status', AgileSprint::STATUS_ACTIVE)->count());
    }

    public function test_only_one_active_sprint_per_board(): void {
        $service = app(AgileSprintService::class);

        $first = $this->plannedSprint();
        $itemA = $this->item('A');
        $service->assign($first, $itemA, $this->lead);
        $service->start($first, $this->lead);

        $second = $this->plannedSprint(['name' => 'Sprint 2']);
        $service->assign($second, $this->item('B'), $this->lead);

        try {
            $service->start($second, $this->lead);
            $this->fail('Zweiter aktiver Sprint wurde zugelassen.');
        } catch (\RuntimeException) {
        }
        $this->assertSame(1, AgileSprint::query()->where('status', AgileSprint::STATUS_ACTIVE)->count());

        // Kein Wiederöffnen: abgeschlossener Sprint kann nicht erneut starten.
        $service->complete($first->fresh(), [(int) $itemA->id => 'backlog'], $this->lead);
        try {
            $service->start($first->fresh(), $this->lead);
            $this->fail('Abgeschlossener Sprint wurde erneut gestartet.');
        } catch (\RuntimeException) {
        }
    }

    public function test_commitment_snapshot_is_immutable(): void {
        $service = app(AgileSprintService::class);
        $item = $this->item('A', 5);

        $sprint = $this->plannedSprint();
        $service->assign($sprint, $item, $this->lead);
        $sprint = $service->start($sprint, $this->lead);

        $this->assertSame(5, $sprint->commitment_snapshot[0]['story_points']);

        // Spätere Punkteänderung ändert den Snapshot NICHT.
        app(AgileWorkItemService::class)->setPoints($item->fresh(), 13, $this->lead);
        $this->assertSame(5, $sprint->fresh()->commitment_snapshot[0]['story_points']);
    }

    public function test_completion_forces_decisions_and_carries_over(): void {
        $sprintService = app(AgileSprintService::class);
        $boardService = app(AgileBoardService::class);

        $doneItem = $this->item('Fertig', 3);
        $openA = $this->item('Offen A', 5);
        $openB = $this->item('Offen B', 8);

        $sprint = $this->plannedSprint();
        foreach ([$doneItem, $openA, $openB] as $item) {
            $sprintService->assign($sprint, $item, $this->lead);
        }
        $sprint = $sprintService->start($sprint, $this->lead);

        $done = $this->board->columns()->where('category', 'done')->firstOrFail();
        $boardService->move($doneItem, $done, (int) $doneItem->fresh()->lock_version, null, $this->lead);

        // Ohne Entscheidungen → Zwangsentscheidung.
        try {
            $sprintService->complete($sprint, [], $this->lead);
            $this->fail('Abschluss ohne Entscheidungen wurde akzeptiert.');
        } catch (\InvalidArgumentException) {
        }

        // Mit Entscheidungen: A → Backlog, B → geplanter Folgesprint.
        $followUp = $this->plannedSprint(['name' => 'Sprint 2']);
        $sprint = $sprintService->complete($sprint->fresh(), [
            (int) $openA->id => 'backlog',
            (int) $openB->id => (string) $followUp->id,
        ], $this->lead);

        $this->assertSame(AgileSprint::STATUS_COMPLETED, $sprint->status);
        $this->assertSame(3, $sprint->completion_snapshot['done_points']);
        $this->assertSame(13, $sprint->completion_snapshot['open_points']);
        $this->assertSame(16, $sprint->completion_snapshot['committed_points']);
        $this->assertTrue($followUp->items()->where('work_item_id', $openB->id)->exists());
        $this->assertSame(1, AgileEvent::query()->where('event', 'sprint.completed')->count());
    }

    public function test_scope_change_after_start_is_marked_and_counted(): void {
        $service = app(AgileSprintService::class);

        $sprint = $this->plannedSprint();
        $service->assign($sprint, $this->item('A'), $this->lead);
        $sprint = $service->start($sprint, $this->lead);

        $late = $this->item('Nachzügler');
        $assignment = $service->assign($sprint, $late, $this->lead);
        $this->assertTrue($assignment->added_after_start);

        $event = AgileEvent::query()->where('event', 'sprint.item_added')
            ->where('work_item_id', $late->id)->firstOrFail();
        $this->assertTrue((bool) $event->payload['added_after_start']);

        $sprint = $service->complete($sprint->fresh(), [
            (int) $sprint->items()->pluck('work_item_id')->map(fn($v) => (int) $v)->first() => 'backlog',
            (int) $late->id => 'backlog',
        ], $this->lead);
        $this->assertSame(1, $sprint->completion_snapshot['scope_added']);
    }

    public function test_method_switch_is_blocked_while_sprint_active(): void {
        $sprintService = app(AgileSprintService::class);
        $sprint = $this->plannedSprint();
        $sprintService->assign($sprint, $this->item(), $this->lead);
        $sprintService->start($sprint, $this->lead);

        $this->expectException(\RuntimeException::class);
        app(AgileBoardService::class)->updateSettings(
            $this->board->fresh(),
            ['method' => 'kanban'],
            (int) $this->board->fresh()->lock_version,
            $this->lead,
        );
    }

    public function test_sprint_pages_and_actions_via_http(): void {
        $sprint = $this->plannedSprint();
        $item = $this->item('HTTP-Story');

        $this->actingAs($this->lead)->get(route('agile.sprints', $this->project))
            ->assertOk()->assertSee('Sprint 1');

        $this->actingAs($this->lead)
            ->post(route('agile.sprints.items.assign', [$this->project, $sprint]), ['item' => $item->fresh()->sqid])
            ->assertRedirect(route('agile.sprints', $this->project));

        $this->actingAs($this->lead)
            ->post(route('agile.sprints.start', [$this->project, $sprint]))
            ->assertRedirect(route('agile.sprints', $this->project));
        $this->assertSame(AgileSprint::STATUS_ACTIVE, $sprint->fresh()->status);

        // Board mit Sprint-Kontext rendert nur Sprint-Items — beide Items
        // liegen in einer Spalte, nur das Sprint-Item erscheint gefiltert.
        $other = $this->item('Nicht im Sprint');
        $ready = $this->board->columns()->orderBy('position')->firstOrFail();
        $boardService = app(\App\Services\Agile\AgileBoardService::class);
        $boardService->move($item->fresh(), $ready, (int) $item->fresh()->lock_version, null, $this->lead);
        $boardService->move($other->fresh(), $ready, (int) $other->fresh()->lock_version, null, $this->lead);

        $this->actingAs($this->lead)
            ->get(route('agile.board', [$this->project, 'sprint' => $sprint->fresh()->sqid]))
            ->assertOk()
            ->assertSee('HTTP-Story')
            ->assertDontSee('Nicht im Sprint');
    }
}
