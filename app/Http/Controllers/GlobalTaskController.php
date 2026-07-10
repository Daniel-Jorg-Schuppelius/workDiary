<?php
/*
 * Created on   : Tue Jun 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : GlobalTaskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Task;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class GlobalTaskController extends Controller {
    private const ALLOWED_SORTS = ['title', 'status', 'priority', 'hourly_rate', 'time_budget', 'billable'];

    public function index(Request $request): View {
        Gate::authorize('viewAny', Task::class);

        $user = $request->user();
        $search = $request->string('q')->toString();
        $sort = in_array($request->string('sort')->toString(), self::ALLOWED_SORTS, true)
            ? $request->string('sort')->toString()
            : 'title';
        $dir = $request->string('dir')->toString() === 'desc' ? 'desc' : 'asc';

        $tasks = Task::query()
            ->where('is_global', true)
            ->when($user?->organization_id, fn($q, $orgId) => $q->where('organization_id', $orgId))
            ->when($search !== '', fn($q) => $q->where(function ($w) use ($search): void {
                $w->whereLikeEscaped('title', $search)
                    ->orWhereLikeEscaped('description', $search);
            }))
            ->orderBy($sort, $dir)
            ->paginate(50)
            ->withQueryString();

        return view('tasks.global.index', compact('tasks', 'search', 'sort', 'dir'));
    }

    public function create(Request $request): View {
        Gate::authorize('create', Task::class);

        $user = $request->user();
        $users = $user?->organization?->users()->orderBy('name')->get(['id', 'name']) ?? collect();

        return view('tasks.global._dialog', [
            'task' => null,
            'users' => $users,
            'isDialog' => true,
        ]);
    }

    public function store(SaveTaskRequest $request): RedirectResponse {
        Gate::authorize('create', Task::class);

        $user = $request->user();
        abort_unless($user && $user->organization_id, 403);

        Task::create(array_merge($request->validated(), [
            'is_global' => true,
            'project_id' => null,
            'parent_task_id' => null,
            'organization_id' => $user->organization_id,
            'created_by' => Auth::id(),
        ]));

        return redirect()->route('tasks.global.index')
            ->with('success', __('Globale Aufgabe angelegt.'));
    }

    public function edit(Request $request, Task $task): View {
        Gate::authorize('update', $task);
        abort_unless($task->is_global, 404);

        $user = $request->user();
        $users = $user?->organization?->users()->orderBy('name')->get(['id', 'name']) ?? collect();

        return view('tasks.global._dialog', [
            'task' => $task,
            'users' => $users,
            'isDialog' => true,
        ]);
    }

    public function update(Task $task, SaveTaskRequest $request): RedirectResponse {
        Gate::authorize('update', $task);
        abort_unless($task->is_global, 404);

        $task->update(array_merge($request->validated(), ['is_global' => true, 'project_id' => null]));

        return redirect()->route('tasks.global.index')
            ->with('success', __('Globale Aufgabe aktualisiert.'));
    }

    public function destroy(Task $task): RedirectResponse {
        Gate::authorize('delete', $task);
        abort_unless($task->is_global, 404);

        $task->delete();

        return redirect()->route('tasks.global.index')
            ->with('success', __('Globale Aufgabe gelöscht.'));
    }
}
