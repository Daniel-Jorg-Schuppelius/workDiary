<?php
/*
 * Created on   : Sat Jul 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileEpicTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Agile;

use App\Enums\Agile\AgileColumnCategory;
use App\Models\Agile\{AgileEvent, AgileWorkItem};
use App\Models\{Organization, Project, User};
use App\Services\Agile\{AgileBoardService, AgileWorkItemService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Vollaudit 2026-07 (M25): Epic-Hierarchie über task.parent_task_id (Feature
 * 064 §Arbeitselement) + Epic-Fortschritt im Sprint-Report (MVP-146).
 */
final class AgileEpicTest extends TestCase {
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

    public function test_assign_epic_sets_parent_task_and_records_event(): void {
        $service = app(AgileWorkItemService::class);
        $epic = $service->create($this->board, ['title' => 'Epos', 'item_type' => 'epic'], $this->lead);
        $story = $service->create($this->board, ['title' => 'Story', 'item_type' => 'story'], $this->lead);

        $service->assignEpic($story, $epic, $this->lead);
        $this->assertSame((int) $epic->task_id, (int) $story->task->fresh()->parent_task_id);
        $this->assertSame(1, AgileEvent::query()->where('event', 'epic.assigned')->count());

        // null löst die Zuordnung wieder.
        $service->assignEpic($story, null, $this->lead);
        $this->assertNull($story->task->fresh()->parent_task_id);
    }

    public function test_assign_epic_guards_type_and_board(): void {
        $service = app(AgileWorkItemService::class);
        $epic = $service->create($this->board, ['title' => 'Epos', 'item_type' => 'epic'], $this->lead);
        $other = $service->create($this->board, ['title' => 'Zweites Epos', 'item_type' => 'epic'], $this->lead);
        $story = $service->create($this->board, ['title' => 'Story', 'item_type' => 'story'], $this->lead);

        // Epics können nicht unter Epics hängen.
        try {
            $service->assignEpic($other, $epic, $this->lead);
            $this->fail('Epic unter Epic wurde nicht abgewiesen.');
        } catch (InvalidArgumentException) {
        }

        // Ziel muss ein Epic sein.
        $plainTask = $service->create($this->board, ['title' => 'Task'], $this->lead);
        $this->expectException(InvalidArgumentException::class);
        $service->assignEpic($story, $plainTask, $this->lead);
    }

    public function test_epic_progress_counts_done_children_and_points(): void {
        $service = app(AgileWorkItemService::class);
        $epic = $service->create($this->board, ['title' => 'Epos', 'item_type' => 'epic'], $this->lead);
        $a = $service->create($this->board, ['title' => 'A', 'item_type' => 'story', 'story_points' => 5], $this->lead);
        $b = $service->create($this->board, ['title' => 'B', 'item_type' => 'story', 'story_points' => 3], $this->lead);
        $service->assignEpic($a, $epic, $this->lead);
        $service->assignEpic($b, $epic, $this->lead);

        $done = $this->board->columns()->where('category', AgileColumnCategory::Done->value)->firstOrFail();
        AgileWorkItem::query()->whereKey($a->id)->update(['column_id' => $done->id]);

        $progress = $service->epicProgress($this->board);
        $this->assertCount(1, $progress);
        $this->assertSame(2, $progress[0]['total']);
        $this->assertSame(1, $progress[0]['done']);
        $this->assertSame(8, $progress[0]['points_total']);
        $this->assertSame(5, $progress[0]['points_done']);
    }

    public function test_epic_endpoint_and_backlog_filter(): void {
        $service = app(AgileWorkItemService::class);
        $epic = $service->create($this->board, ['title' => 'Epos', 'item_type' => 'epic'], $this->lead);
        $story = $service->create($this->board, ['title' => 'Story', 'item_type' => 'story'], $this->lead);
        $service->create($this->board, ['title' => 'Ohne Epic', 'item_type' => 'story'], $this->lead);

        $this->actingAs($this->lead)
            ->patch(route('agile.items.epic', [$this->board->project, $story]), ['epic' => $epic->sqid])
            ->assertRedirect(route('agile.backlog', $this->board->project));
        $this->assertSame((int) $epic->task_id, (int) $story->task->fresh()->parent_task_id);

        $response = $this->actingAs($this->lead)
            ->get(route('agile.backlog', [$this->board->project, 'epic' => $epic->sqid]))
            ->assertOk();
        $items = $response->viewData('items');
        $this->assertCount(1, $items);
        $this->assertSame((int) $story->id, (int) $items->first()->id);
    }
}
