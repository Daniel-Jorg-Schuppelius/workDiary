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
use App\Models\{Project, User};
use App\Services\Agile\{AgileBoardService, AgileConflictException};
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

        $board?->load(['columns.workItems' => function ($query) use ($sprint): void {
            // Gebuchte Zeit je Task (P6) — Story Points werden NIE in
            // Zeit/€ umgerechnet, beides steht getrennt auf der Karte.
            $query->with(['task' => fn($q) => $q->withSum('timeEntries', 'minutes')])
                ->orderBy('backlog_rank');
            if ($sprint !== null) {
                $query->whereHas('sprintItems', fn($q) => $q->where('sprint_id', $sprint->id));
            }
        }]);

        return view('agile.board', [
            'project' => $project,
            'board' => $board,
            'sprint' => $sprint,
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

        $dodItems = array_values(array_filter(array_map('trim', explode("\n", (string) ($data['dod_items'] ?? '')))));

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
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.board', $project)
            ->with('success', __('Board-Einstellungen gespeichert.'));
    }

    /** Karte verschieben (JSON für Alpine-Fetch, Form-Fallback ohne JS). */
    public function moveItem(Request $request, Project $project, \App\Models\Agile\AgileWorkItem $item): \Symfony\Component\HttpFoundation\Response {
        abort_unless((int) ($item->board?->project_id) === (int) $project->id, 404);
        Gate::authorize('move', $item);

        $data = $request->validate([
            'column' => ['required', 'string', 'max:64'],
            'lock_version' => ['required', 'integer', 'min:1'],
            'override_reason' => ['nullable', 'string', 'max:300'],
        ]);

        $columnId = \App\Support\Sqid::decode(\App\Models\Agile\AgileBoardColumn::class, (string) $data['column']);
        $target = \App\Models\Agile\AgileBoardColumn::query()
            ->where('board_id', $item->board_id)
            ->findOrFail($columnId);

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
            );
        } catch (\App\Services\Agile\AgileConflictException $e) {
            abort(409, $e->getMessage());
        } catch (\App\Services\Agile\AgileFlowException $e) {
            if ($request->expectsJson()) {
                return response()->json(['error' => $e->reason, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
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
            return back()->with('error', $e->getMessage());
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
            return back()->with('error', $e->getMessage());
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
