<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileIntegrationTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\AgileWorkItem;
use App\Models\{Organization, Project, Task, TimeEntry, User};
use App\Services\Agile\{AgileBoardService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 064, P6 (MVP-144): Bestand bleibt regressionsfrei — Projekt-Detail
 * (_tasks_tab) und Gantt rendern mit aktivem Board; Task-Anlage (Importe)
 * erzeugt NIE automatisch ein Arbeitselement (adopt ist der einzige Weg);
 * Status-Roundtrip Task↔Board bleibt stabil; Board-Karte zeigt gebuchte
 * Zeit (Story Points werden nie umgerechnet).
 */
final class AgileIntegrationTest extends TestCase {
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
        $this->board = app(AgileBoardService::class)->activate($this->project, actor: $this->lead);
    }

    public function test_project_show_and_gantt_render_with_active_board(): void {
        $item = app(AgileWorkItemService::class)->create($this->board, ['title' => 'Board-Story'], $this->lead);

        $this->actingAs($this->lead)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('Board-Story');

        $this->actingAs($this->lead)->get(route('projects.planning', $this->project))
            ->assertOk();

        $this->assertNotNull($item->fresh());
    }

    public function test_plain_task_creation_never_creates_a_work_item(): void {
        // Importer/Formulare erzeugen nur Tasks — adopt() ist der einzige
        // Weg ins Board; auch Statuswechsel nicht-adoptierter Tasks sind ok.
        $task = Task::factory()->create([
            'organization_id' => $this->project->organization_id,
            'project_id' => $this->project->id,
        ]);
        $this->assertSame(0, AgileWorkItem::query()->count());

        $task->forceFill(['status' => 'done'])->save();
        $this->assertSame(0, AgileWorkItem::query()->count());

        // Adoption bleibt idempotent — keine Dublette bei erneutem Import-Lauf.
        $service = app(AgileWorkItemService::class);
        $service->adopt($this->board, $task->fresh(), $this->lead);
        $service->adopt($this->board, $task->fresh(), $this->lead);
        $this->assertSame(1, AgileWorkItem::query()->count());
    }

    public function test_status_roundtrip_between_task_and_board_stays_stable(): void {
        $boards = app(AgileBoardService::class);
        $item = app(AgileWorkItemService::class)->create($this->board, ['title' => 'Roundtrip'], $this->lead);
        $done = $this->board->columns()->where('category', 'done')->firstOrFail();

        // Board → Task.
        $boards->move($item->fresh(), $done, (int) $item->fresh()->lock_version, null, $this->lead);
        $task = $item->task()->firstOrFail();
        $this->assertSame('done', (string) $task->getAttribute('status')?->value);

        // Task → Board (Reopen) → Task → Board: keine Schleife, Zustand stabil.
        $task->forceFill(['status' => 'open'])->save();
        $this->assertSame('open', $item->fresh()->column?->category?->value);

        $task->fresh()->forceFill(['status' => 'done'])->save();
        $fresh = $item->fresh(['column']);
        $this->assertSame('done', $fresh->column?->category?->value);
        $this->assertSame('done', (string) $task->fresh()->getAttribute('status')?->value);
    }

    public function test_board_card_shows_booked_time_without_converting_points(): void {
        $item = app(AgileWorkItemService::class)->create($this->board, [
            'title' => 'Mit Zeit',
            'story_points' => 8,
        ], $this->lead);
        $ready = $this->board->columns()->orderBy('position')->firstOrFail();
        app(AgileBoardService::class)->move($item->fresh(), $ready, (int) $item->fresh()->lock_version, null, $this->lead);

        TimeEntry::factory()->create([
            'organization_id' => $this->project->organization_id,
            'user_id' => $this->lead->id,
            'task_id' => $item->task_id,
            'minutes' => 90,
        ]);

        $this->actingAs($this->lead)->get(route('agile.board', $this->project))
            ->assertOk()
            ->assertSee('8 SP')
            ->assertSee('1:30');
    }
}
