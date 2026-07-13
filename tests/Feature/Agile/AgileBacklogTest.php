<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBacklogTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Models\Agile\{AgileEvent, AgileWorkItem};
use App\Models\{Organization, Project, Task, User};
use App\Services\Agile\{AgileBoardService, AgileConflictException, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature 064, P2 (MVP-140): adopt idempotent, create transaktional
 * (Task+Item), Rang-Neuverteilung deterministisch, Events im festen
 * Katalog, 409-Pfad bei Rang-Konflikt.
 */
final class AgileBacklogTest extends TestCase {
    use RefreshDatabase;

    private \App\Models\Agile\AgileBoard $board;

    private User $lead;

    protected function setUp(): void {
        parent::setUp();
        $org = Organization::factory()->create();
        app()->instance('currentOrganization', $org);
        $this->lead = User::factory()->teamleitung()->create(['organization_id' => $org->id]);
        $project = Project::factory()->create(['organization_id' => $org->id]);
        $this->board = app(AgileBoardService::class)->activate($project, actor: $this->lead);
    }

    public function test_adopt_is_idempotent_and_records_event(): void {
        $task = Task::factory()->create([
            'organization_id' => $this->board->organization_id,
            'project_id' => $this->board->project_id,
        ]);

        $service = app(AgileWorkItemService::class);
        $item = $service->adopt($this->board, $task, $this->lead);
        $again = $service->adopt($this->board, $task, $this->lead);

        $this->assertSame($item->id, $again->id);
        $this->assertSame(1, AgileWorkItem::query()->count());
        $this->assertSame(1, AgileEvent::query()->where('event', 'backlog.added')->count());
    }

    /** B1/MVP-007: das Übernehmen-Formular sendet die Task-Sqid (Konvention: Sqid in Formularen). */
    public function test_adopt_endpoint_accepts_task_sqid(): void {
        $task = Task::factory()->create([
            'organization_id' => $this->board->organization_id,
            'project_id' => $this->board->project_id,
        ]);

        $this->actingAs($this->lead)
            ->post(route('agile.items.adopt', $this->board->project), [
                'task_id' => $task->sqid,
            ])->assertRedirect(route('agile.backlog', $this->board->project));

        $this->assertSame(1, AgileWorkItem::query()->where('task_id', $task->id)->count());
    }

    public function test_create_makes_task_and_item_in_one_transaction(): void {
        $item = app(AgileWorkItemService::class)->create($this->board, [
            'title' => 'Als Nutzer möchte ich …',
            'item_type' => 'story',
            'story_points' => 5,
        ], $this->lead);

        $this->assertSame('story', $item->item_type->value);
        $this->assertSame(5, $item->story_points);
        $this->assertNotNull($item->task);
        $this->assertSame('Als Nutzer möchte ich …', $item->task->title);
        $this->assertSame((int) $this->board->project_id, (int) $item->task->project_id);
    }

    public function test_rerank_uses_gaps_and_redistributes_deterministically(): void {
        $service = app(AgileWorkItemService::class);
        $a = $service->create($this->board, ['title' => 'A'], $this->lead);
        $b = $service->create($this->board, ['title' => 'B'], $this->lead);
        $c = $service->create($this->board, ['title' => 'C'], $this->lead);

        // C an die Spitze (vor A).
        $service->rerank($c->fresh(), null, 1, $this->lead);
        $order = AgileWorkItem::query()->orderBy('backlog_rank')->pluck('task_id');
        $this->assertSame([(int) $c->task_id, (int) $a->task_id, (int) $b->task_id], $order->map(fn($v) => (int) $v)->all());

        // Kollision erzwingen: Lücke zwischen C(500) und A(1000) mehrfach halbieren.
        for ($i = 0; $i < 12; $i++) {
            $mover = AgileWorkItem::query()->orderByDesc('backlog_rank')->first();
            $top = AgileWorkItem::query()->orderBy('backlog_rank')->first();
            app(AgileWorkItemService::class)->rerank($mover, $top, (int) $mover->lock_version, $this->lead);
        }

        // Ordnung bleibt strikt (keine Duplikate) — Neuverteilung griff.
        $ranks = AgileWorkItem::query()->orderBy('backlog_rank')->pluck('backlog_rank')->all();
        $this->assertSame($ranks, array_values(array_unique($ranks)));
        $this->assertSame(1, AgileEvent::query()->where('event', 'backlog.reranked')->count() > 0 ? 1 : 0);
    }

    public function test_rerank_conflict_throws(): void {
        $service = app(AgileWorkItemService::class);
        $a = $service->create($this->board, ['title' => 'A'], $this->lead);
        $service->create($this->board, ['title' => 'B'], $this->lead);

        $service->rerank($a->fresh(), null, 1, $this->lead); // lock_version → 2

        $this->expectException(AgileConflictException::class);
        $service->rerank($a->fresh(), null, 1, $this->lead); // veraltete Version
    }

    public function test_backlog_page_renders_and_moves_via_http(): void {
        $service = app(AgileWorkItemService::class);
        $a = $service->create($this->board, ['title' => 'Alpha'], $this->lead);
        $b = $service->create($this->board, ['title' => 'Beta'], $this->lead);

        $project = $this->board->project()->firstOrFail();

        $this->actingAs($this->lead)->get(route('agile.backlog', $project))
            ->assertOk()
            ->assertSee('Alpha')
            ->assertSee('Beta');

        // Beta an die Spitze (after leer).
        $this->actingAs($this->lead)
            ->patch(route('agile.items.rerank', [$project, $b]), [
                'after' => '',
                'lock_version' => $b->fresh()->lock_version,
            ])->assertRedirect(route('agile.backlog', $project));

        $order = AgileWorkItem::query()->orderBy('backlog_rank')->pluck('task_id')->map(fn($v) => (int) $v)->all();
        $this->assertSame([(int) $b->task_id, (int) $a->task_id], $order);

        // Veraltete lock_version → 409.
        $this->actingAs($this->lead)
            ->patch(route('agile.items.rerank', [$project, $b]), [
                'after' => '',
                'lock_version' => 1,
            ])->assertStatus(409);
    }

    public function test_criteria_crud_and_toggle(): void {
        $item = app(AgileWorkItemService::class)->create($this->board, ['title' => 'Story'], $this->lead);
        $project = $this->board->project()->firstOrFail();

        $this->actingAs($this->lead)
            ->post(route('agile.criteria.store', [$project, $item]), ['text' => 'Login funktioniert'])
            ->assertRedirect();

        $criterion = \App\Models\Agile\AgileAcceptanceCriterion::query()->firstOrFail();
        $this->assertSame(1, $criterion->position);

        $this->actingAs($this->lead)
            ->patch(route('agile.criteria.toggle', [$project, $item, $criterion]))
            ->assertRedirect();
        $this->assertNotNull($criterion->fresh()->checked_at);

        $this->actingAs($this->lead)
            ->delete(route('agile.criteria.destroy', [$project, $item, $criterion]))
            ->assertRedirect();
        $this->assertSame(0, \App\Models\Agile\AgileAcceptanceCriterion::query()->count());
    }

    public function test_points_change_records_event(): void {
        $item = app(AgileWorkItemService::class)->create($this->board, ['title' => 'A'], $this->lead);

        app(AgileWorkItemService::class)->setPoints($item, 8, $this->lead);

        $event = AgileEvent::query()->where('event', 'points.changed')->firstOrFail();
        $this->assertSame(['from' => null, 'to' => 8], $event->payload);
    }
}
