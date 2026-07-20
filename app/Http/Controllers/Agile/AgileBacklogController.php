<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBacklogController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Agile;

use App\Enums\Agile\AgileItemType;
use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agile\{AgileAcceptanceCriterion, AgileBoard, AgileWorkItem};
use App\Models\{Project, Task, User};
use App\Services\Agile\{AgileConflictException, AgileWorkItemService};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Produkt-Backlog (Feature 064, MVP-140): Rangliste mit Filtern,
 * Neuanlage/Adoption, Rang-Verschiebung (Hoch/Runter — A11y; derselbe
 * Endpoint trägt später das Drag-JS), Story Points, Typ und
 * Akzeptanzkriterien. Rangänderung konfliktgeschützt (lock_version → 409).
 */
class AgileBacklogController extends Controller {
    public function __construct(private readonly AgileWorkItemService $items) {}

    public function index(Request $request, Project $project): View {
        Gate::authorize(Permission::AgileView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();

        $query = AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->with(['task.assignees', 'column'])
            ->orderBy('backlog_rank');

        $type = trim((string) $request->query('type', ''));
        if (AgileItemType::tryFrom($type) !== null) {
            $query->where('item_type', $type);
        }
        if ($request->query('blocked') === '1') {
            $query->whereNotNull('blocked_at');
        }
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->whereHas('task', fn($q) => $q->whereLikeEscaped('title', $search));
        }

        // Epic-Hierarchie (Vollaudit 2026-07, M25): Epics des Boards für
        // Zuordnungs-Select + optionaler Filter auf die Kinder eines Epics.
        $epics = AgileWorkItem::query()
            ->where('board_id', $board->id)
            ->where('item_type', AgileItemType::Epic->value)
            ->with('task:id,title')
            ->orderBy('backlog_rank')
            ->get();

        $epicFilter = null;
        if (trim((string) $request->query('epic', '')) !== '') {
            $epicId = \App\Support\Sqid::decode(AgileWorkItem::class, (string) $request->query('epic'));
            $epicFilter = $epics->firstWhere('id', $epicId);
            if ($epicFilter !== null) {
                $query->whereHas('task', fn($q) => $q->where('parent_task_id', $epicFilter->task_id));
            }
        }

        return view('agile.backlog', [
            'project' => $project,
            'board' => $board,
            'items' => $query->get(),
            'epics' => $epics,
            'epicByTaskId' => $epics->keyBy('task_id'),
            'epicFilter' => $epicFilter,
            'adoptableTasks' => Task::query()
                ->where('project_id', $project->id)
                ->whereDoesntHave('agileWorkItem')
                ->orderBy('title')
                ->limit(100)
                ->get(['id', 'title']),
            'filters' => ['type' => $type, 'q' => $search, 'blocked' => (string) $request->query('blocked', ''), 'epic' => $epicFilter->sqid ?? ''],
            'canPrioritize' => Gate::allows(Permission::AgileBacklogPrioritize->value),
            'canManage' => Gate::allows('manage', $board),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse {
        $board = $this->boardFor($project);
        Gate::authorize('manage', $board);

        $data = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'item_type' => ['required', 'in:epic,story,task,bug'],
            'story_points' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->items->create($board, $data, $actor);

        return redirect()->route('agile.backlog', $project)
            ->with('success', __('Arbeitselement angelegt.'));
    }

    public function adopt(Request $request, Project $project): RedirectResponse {
        $board = $this->boardFor($project);
        Gate::authorize('manage', $board);

        $request->merge(['task_id' => \App\Support\Sqid::decodeOrNumeric(Task::class, $request->input('task_id'))]);
        $request->validate(['task_id' => ['required', 'integer']]);
        $task = Task::query()
            ->where('project_id', $project->id)
            ->findOrFail((int) $request->input('task_id'));

        /** @var User $actor */
        $actor = Auth::user();
        $this->items->adopt($board, $task, $actor);

        return redirect()->route('agile.backlog', $project)
            ->with('success', __('Aufgabe ins Backlog übernommen.'));
    }

    /** Rang ändern: after = Sqid des Vorgängers (leer = Spitze). */
    public function rerank(Request $request, Project $project, AgileWorkItem $item): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('prioritize', $item);

        $data = $request->validate([
            'after' => ['nullable', 'string', 'max:64'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $after = null;
        if (($data['after'] ?? '') !== '') {
            $afterId = \App\Support\Sqid::decode(AgileWorkItem::class, (string) $data['after']);
            $after = AgileWorkItem::query()->where('board_id', $item->board_id)->find($afterId);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->items->rerank($item, $after, (int) $data['lock_version'], $actor);
        } catch (AgileConflictException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.backlog', $project);
    }

    public function updateItem(Request $request, Project $project, AgileWorkItem $item): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('prioritize', $item);

        $data = $request->validate([
            'story_points' => ['nullable', 'integer', 'min:1', 'max:999'],
            'item_type' => ['nullable', 'in:epic,story,task,bug'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        if ($request->has('story_points')) {
            $this->items->setPoints($item, $data['story_points'] !== null ? (int) $data['story_points'] : null, $actor);
        }
        if (($data['item_type'] ?? null) !== null) {
            $this->items->setType($item->fresh() ?? $item, AgileItemType::from($data['item_type']));
        }

        return redirect()->route('agile.backlog', $project)
            ->with('success', __('Arbeitselement aktualisiert.'));
    }

    /** Epic zuordnen/lösen (Vollaudit 2026-07, M25): epic = Sqid, leer = lösen. */
    public function assignEpic(Request $request, Project $project, AgileWorkItem $item): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('prioritize', $item);

        $data = $request->validate(['epic' => ['nullable', 'string', 'max:64']]);

        $epic = null;
        if (($data['epic'] ?? '') !== '') {
            $epicId = \App\Support\Sqid::decode(AgileWorkItem::class, (string) $data['epic']);
            $epic = AgileWorkItem::query()->where('board_id', $item->board_id)->findOrFail($epicId);
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->items->assignEpic($item, $epic, $actor);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.backlog', $project)
            ->with('success', __('Epic-Zuordnung aktualisiert.'));
    }

    public function storeCriterion(Request $request, Project $project, AgileWorkItem $item): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('prioritize', $item);

        $data = $request->validate(['text' => ['required', 'string', 'min:2', 'max:500']]);

        $position = (int) AgileAcceptanceCriterion::query()->where('work_item_id', $item->id)->max('position') + 1;
        AgileAcceptanceCriterion::query()->create([
            'organization_id' => $item->organization_id,
            'work_item_id' => $item->id,
            'position' => $position,
            'text' => $data['text'],
        ]);

        return back()->with('success', __('Kriterium hinzugefügt.'));
    }

    public function toggleCriterion(Project $project, AgileWorkItem $item, AgileAcceptanceCriterion $criterion): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('move', $item);
        abort_unless((int) $criterion->work_item_id === (int) $item->id, 404);

        /** @var User $actor */
        $actor = Auth::user();
        $criterion->update($criterion->checked_at === null
            ? ['checked_at' => now(), 'checked_by' => $actor->id]
            : ['checked_at' => null, 'checked_by' => null]);

        return back();
    }

    public function destroyCriterion(Project $project, AgileWorkItem $item, AgileAcceptanceCriterion $criterion): RedirectResponse {
        $this->assertItemOnProject($item, $project);
        Gate::authorize('prioritize', $item);
        abort_unless((int) $criterion->work_item_id === (int) $item->id, 404);

        $criterion->delete();

        return back()->with('success', __('Kriterium entfernt.'));
    }

    private function boardFor(Project $project): AgileBoard {
        return AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
    }

    private function assertItemOnProject(AgileWorkItem $item, Project $project): void {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
    }
}
