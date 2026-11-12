<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileBoardController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Agile;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agile\AgileBoard;
use App\Models\Platform\User;
use App\Models\Project\Project;
use App\Services\Agile\{AgileBoardService, AgileConflictException};
use App\Support\ErrorText;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use RuntimeException;

/**
 * Projektboard (Feature 064, MVP-139): Aktivierung je Projekt, Board-Sicht
 * (Spaltenfundament — Ausbau in P3), Einstellungen mit optimistischer
 * Sperre (409 bei lock_version-Konflikt). Eigenes agile.*-Präfix
 * (module.agile_projects — projects.* ist auf module.vertrieb gemappt).
 */
class AgileBoardController extends Controller {
    public function __construct(private readonly AgileBoardService $boards) {}

    public function board(Request $request, Project $project): View {
        Gate::authorize(Permission::AgileView->value);
        Gate::authorize('view', $project);

        $board = AgileBoard::query()
            ->where('project_id', $project->id)
            ->first();

        // Sprint-Kontext (P4): Board zeigt dann nur die Items des Sprints.
        $sprint = null;
        if ($board !== null && trim((string) $request->query('sprint', '')) !== '') {
            $sprintId = \App\Support\Sqid::decode(\App\Models\Agile\AgileSprint::class, (string) $request->query('sprint'));
            $sprint = \App\Models\Agile\AgileSprint::query()->where('board_id', $board->id)->findOrFail($sprintId);
        }

        /** @var User $viewer */
        $viewer = Auth::user();

        $board?->load(['columns.workItems' => function ($query) use ($sprint, $viewer): void {
            // Gebuchte Zeit je Task (P6) — Story Points werden NIE in
            // Zeit/€ umgerechnet, beides steht getrennt auf der Karte. Ohne
            // Sicht auf alle Zeiten zählt die Summe nur die eigenen Einträge.
            $query->with(['task' => fn($q) => $q->withSum(['timeEntries' => fn($t) => $t->visibleTo($viewer)], 'minutes')])
                ->orderBy('backlog_rank');
            if ($sprint !== null) {
                $query->whereHas('sprintItems', fn($q) => $q->where('sprint_id', $sprint->id));
            }
        }]);

        return view('agile.board', [
            'project' => $project,
            'board' => $board,
            'sprint' => $sprint,
            'seesAllTimes' => $viewer->canViewAllTimeEntries(),
            'sprints' => $board !== null
                ? \App\Models\Agile\AgileSprint::query()->where('board_id', $board->id)->orderByDesc('id')->get(['id', 'name', 'status'])
                : collect(),
            'canManage' => $board !== null
                ? Gate::allows('manage', $board)
                : Gate::allows('activate', [AgileBoard::class, $project]),
        ]);
    }

    public function activate(Request $request, Project $project): RedirectResponse {
        Gate::authorize('activate', [AgileBoard::class, $project]);

        $data = $request->validate([
            'method' => ['nullable', 'in:kanban,scrum'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->boards->activate($project, $data['method'] ?? AgileBoard::METHOD_KANBAN, $actor);

        return redirect()->route('agile.board', $project)
            ->with('success', __('Projektboard aktiviert.'));
    }

    public function updateSettings(Request $request, Project $project): RedirectResponse {
        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        Gate::authorize('manage', $board);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'method' => ['required', 'in:kanban,scrum'],
            'dod_items' => ['nullable', 'string', 'max:2000'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);

        $dodItems = array_map('trim', StringHelper::lines((string) ($data['dod_items'] ?? ''), true));

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->boards->updateSettings($board, [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'method' => $data['method'],
                'dod_items' => $dodItems,
            ], (int) $data['lock_version'], $actor);
        } catch (AgileConflictException $e) {
            abort(409, $e->getMessage());
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('agile.board', $project)
            ->with('success', __('Board-Einstellungen gespeichert.'));
    }

    /** Abschlussdialog: offene Kriterien, DoD-Punkte zum Bestätigen, Begründung fürs Übersteuern. */
    public function moveDialog(Request $request, Project $project, \App\Models\Agile\AgileWorkItem $item): View {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
        Gate::authorize('move', $item);

        $target = $this->targetColumn($item, (string) $request->query('column'));
        $wipReached = $target->wip_limit !== null
            && (int) $target->id !== (int) $item->column_id
            && \App\Models\Agile\AgileWorkItem::query()->where('column_id', $target->id)->where('id', '!=', $item->id)->count() >= $target->wip_limit;
        $entersDone = $target->category === \App\Enums\Agile\AgileColumnCategory::Done;

        return view('agile._move_dialog', [
            'project' => $project,
            'item' => $item->load('task:id,title'),
            'target' => $target,
            'wipReached' => $wipReached,
            'openCriteria' => $entersDone ? $item->acceptanceCriteria()->whereNull('checked_at')->get() : collect(),
            'dodItems' => $entersDone && $item->column?->category !== \App\Enums\Agile\AgileColumnCategory::Done
                ? array_values((array) ($item->board->dod_items ?? []))
                : [],
            'mayOverride' => Gate::allows(Permission::AgileWorkflowOverride->value),
        ]);
    }

    private function targetColumn(\App\Models\Agile\AgileWorkItem $item, string $sqid): \App\Models\Agile\AgileBoardColumn {
        return \App\Models\Agile\AgileBoardColumn::query()
            ->where('board_id', $item->board_id)
            ->findOrFail(\App\Support\Sqid::decode(\App\Models\Agile\AgileBoardColumn::class, $sqid));
    }

    /** Karte verschieben (JSON für Alpine-Fetch, Form-Fallback ohne JS). */
    public function moveItem(Request $request, Project $project, \App\Models\Agile\AgileWorkItem $item): \Symfony\Component\HttpFoundation\Response {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
        Gate::authorize('move', $item);

        $data = $request->validate([
            'column' => ['required', 'string', 'max:64'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:300'],
            'dod_confirmed' => ['nullable', 'array', 'max:100'],
            'dod_confirmed.*' => ['integer', 'min:0'],
        ]);

        $target = $this->targetColumn($item, (string) $data['column']);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $fresh = $this->boards->move(
                $item,
                $target,
                (int) $data['lock_version'],
                $data['override_reason'] ?? null,
                $actor,
                Gate::allows(Permission::AgileWorkflowOverride->value),
                array_map(intval(...), array_values((array) ($data['dod_confirmed'] ?? []))),
            );
        } catch (\App\Services\Agile\AgileConflictException $e) {
            abort(409, $e->getMessage());
        } catch (\App\Services\Agile\AgileFlowException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->reason, 'message' => $e->getMessage(), 'errors' => ['column' => [$e->getMessage()]]], 422);
            }

            // Der Abschlussdialog zeigt, was offen ist, und nimmt Bestätigung bzw. Begründung an.
            return back()->with('warning', $e->getMessage())
                ->with('open_dialog', route('agile.items.move.dialog', [$project, $item, 'column' => $target->sqid]));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'lock_version' => $fresh->lock_version,
                'column_counts' => \App\Models\Agile\AgileWorkItem::query()
                    ->where('board_id', $item->board_id)
                    ->whereNotNull('column_id')
                    ->selectRaw('column_id, count(*) as c')
                    ->groupBy('column_id')
                    ->pluck('c', 'column_id'),
            ]);
        }

        return redirect()->route('agile.board', $project);
    }

    /** Spalte anlegen oder ändern (P3: Name/Kategorie/WIP/Position). */
    public function saveColumn(Request $request, Project $project, ?\App\Models\Agile\AgileBoardColumn $column = null): RedirectResponse {
        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        Gate::authorize('manage', $board);
        if ($column !== null) {
            abort_unless((int) $column->board_id === (int) $board->id, 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'category' => ['required', 'in:open,in_progress,done'],
            'wip_limit' => ['nullable', 'integer', 'min:1', 'max:99'],
            'position' => ['nullable', 'integer', 'min:1', 'max:50'],
            'report_role' => ['nullable', 'in:working,waiting'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->boards->saveColumn($board, $data, $column, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('agile.board', $project)
            ->with('success', __('Spalte gespeichert.'));
    }

    public function destroyColumn(Project $project, \App\Models\Agile\AgileBoardColumn $column): RedirectResponse {
        $board = AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
        Gate::authorize('manage', $board);
        abort_unless((int) $column->board_id === (int) $board->id, 404);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->boards->deleteColumn($column, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return redirect()->route('agile.board', $project)
            ->with('success', __('Spalte gelöscht.'));
    }

    public function blockItem(Request $request, Project $project, \App\Models\Agile\AgileWorkItem $item): RedirectResponse {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
        Gate::authorize('move', $item);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->boards->block($item, $data['reason'], $actor);

        return back()->with('success', __('Element blockiert.'));
    }

    public function unblockItem(Project $project, \App\Models\Agile\AgileWorkItem $item): RedirectResponse {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
        Gate::authorize('move', $item);

        /** @var User $actor */
        $actor = Auth::user();
        $this->boards->unblock($item, $actor);

        return back()->with('success', __('Blockierung aufgehoben.'));
    }
}
