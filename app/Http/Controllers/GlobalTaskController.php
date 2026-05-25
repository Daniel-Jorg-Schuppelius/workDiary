<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Task;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class GlobalTaskController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', Task::class);

        $user = $request->user();
        $tasks = Task::query()
            ->where('is_global', true)
            ->when($user?->organization_id, fn($q, $orgId) => $q->where('organization_id', $orgId))
            ->orderBy('position')
            ->orderBy('title')
            ->paginate(50);

        return view('tasks.global.index', ['tasks' => $tasks]);
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
