<?php
/*
 * Created on   : Wed Jul 08 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AgileSprintController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Agile;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Agile\{AgileBoard, AgileSprint, AgileWorkItem};
use App\Models\{Project, User};
use App\Services\Agile\AgileSprintService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Sprint-Lebenszyklus (Feature 064, MVP-142): Planung, Zuordnung,
 * Start/Abschluss/Abbruch. Abschluss verlangt je unerledigtem Element
 * eine explizite Entscheidung (kein Default) — Regeln liegen im Service.
 */
class AgileSprintController extends Controller {
    public function __construct(private readonly AgileSprintService $sprints) {}

    public function index(Project $project): View {
        Gate::authorize(Permission::AgileView->value);
        Gate::authorize('view', $project);

        $board = $this->boardFor($project);

        return view('agile.sprints', [
            'project' => $project,
            'board' => $board,
            'sprints' => AgileSprint::query()
                ->where('board_id', $board->id)
                ->with(['items.workItem.task', 'items.workItem.column'])
                ->orderByDesc('id')
                ->get(),
            'assignableItems' => AgileWorkItem::query()
                ->where('board_id', $board->id)
                ->with('task')
                ->orderBy('backlog_rank')
                ->limit(200)
                ->get(),
            'canManage' => Gate::allows(Permission::AgileSprintManage->value),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse {
        $board = $this->authorizeManage($project);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'goal' => ['nullable', 'string', 'max:500'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $this->sprints->plan($board, $data, $actor);

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Sprint geplant.'));
    }

    public function assignItem(Request $request, Project $project, AgileSprint $sprint): RedirectResponse {
        $this->authorizeManage($project, $sprint);

        $request->validate(['item' => ['required', 'string', 'max:64']]);
        $itemId = \App\Support\Sqid::decode(AgileWorkItem::class, (string) $request->input('item'));
        $item = AgileWorkItem::query()->where('board_id', $sprint->board_id)->findOrFail($itemId);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->sprints->assign($sprint, $item, $actor);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Element dem Sprint zugeordnet.'));
    }

    public function removeItem(Project $project, AgileSprint $sprint, AgileWorkItem $item): RedirectResponse {
        $this->authorizeManage($project, $sprint);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->sprints->remove($sprint, $item, $actor);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Element aus dem Sprint entfernt.'));
    }

    public function start(Request $request, Project $project, AgileSprint $sprint): RedirectResponse {
        $this->authorizeManage($project, $sprint);

        $data = $request->validate([
            'capacity_adjustment_hours' => ['nullable', 'numeric', 'min:-999', 'max:999'],
            'capacity_adjustment_reason' => ['nullable', 'string', 'max:300', 'required_with:capacity_adjustment_hours'],
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->sprints->start(
                $sprint,
                $actor,
                (float) ($data['capacity_adjustment_hours'] ?? 0),
                $data['capacity_adjustment_reason'] ?? null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Sprint gestartet.'));
    }

    /** decisions[work_item_id] = 'backlog' | Sprint-Sqid des Folgesprints. */
    public function complete(Request $request, Project $project, AgileSprint $sprint): RedirectResponse {
        $this->authorizeManage($project, $sprint);

        $raw = (array) $request->input('decisions', []);
        $decisions = [];
        foreach ($raw as $workItemId => $decision) {
            $decision = (string) $decision;
            if ($decision !== '' && $decision !== 'backlog') {
                $decision = (string) \App\Support\Sqid::decode(AgileSprint::class, $decision);
            }
            if ($decision !== '') {
                $decisions[(int) $workItemId] = $decision;
            }
        }

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->sprints->complete($sprint, $decisions, $actor);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Sprint abgeschlossen.'));
    }

    public function cancel(Request $request, Project $project, AgileSprint $sprint): RedirectResponse {
        $this->authorizeManage($project, $sprint);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        /** @var User $actor */
        $actor = Auth::user();

        try {
            $this->sprints->cancel($sprint, $data['reason'], $actor);
        } catch (RuntimeException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agile.sprints', $project)
            ->with('success', __('Sprint abgebrochen.'));
    }

    private function boardFor(Project $project): AgileBoard {
        return AgileBoard::query()->where('project_id', $project->id)->firstOrFail();
    }

    private function authorizeManage(Project $project, ?AgileSprint $sprint = null): AgileBoard {
        Gate::authorize(Permission::AgileSprintManage->value);
        Gate::authorize('view', $project);

        $board = $this->boardFor($project);
        if ($sprint !== null) {
            abort_unless((int) $sprint->board_id === (int) $board->id, 404);
        }

        return $board;
    }
}
