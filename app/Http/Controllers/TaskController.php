<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTaskRequest;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function create(Project $project, Request $request): View
    {
        Gate::authorize('create', Task::class);

        $milestones = $project->milestones()->orderBy('position')->orderBy('due_date')->get(['id', 'title']);
        $parentTasks = $project->tasks()->whereNull('parent_task_id')->orderBy('position')->get(['id', 'title']);
        $users = $project->organization
            ? $project->organization->users()->orderBy('name')->get(['id', 'name'])
            : collect();

        $preselectedParentId = $request->integer('parent_id') ?: null;

        return view('projects._task_dialog', [
            'project' => $project,
            'task' => null,
            'milestones' => $milestones,
            'parentTasks' => $parentTasks,
            'users' => $users,
            'preselectedParentId' => $preselectedParentId,
            'isDialog' => true,
        ]);
    }

    public function store(Project $project, SaveTaskRequest $request): RedirectResponse
    {
        Gate::authorize('create', Task::class);

        $data = $request->validated();

        $project->tasks()->create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe angelegt.'));
    }

    public function edit(Project $project, Task $task): View
    {
        Gate::authorize('update', $task);

        $milestones = $project->milestones()->orderBy('position')->orderBy('due_date')->get(['id', 'title']);
        $parentTasks = $project->tasks()
            ->whereNull('parent_task_id')
            ->where('id', '!=', $task->id)
            ->orderBy('position')
            ->get(['id', 'title']);
        $users = $project->organization
            ? $project->organization->users()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('projects._task_dialog', [
            'project' => $project,
            'task' => $task,
            'milestones' => $milestones,
            'parentTasks' => $parentTasks,
            'users' => $users,
            'preselectedParentId' => null,
            'isDialog' => true,
        ]);
    }

    public function update(Project $project, Task $task, SaveTaskRequest $request): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update($request->validated());

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe aktualisiert.'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe gelöscht.'));
    }

    public function complete(Project $project, Task $task): RedirectResponse
    {
        Gate::authorize('update', $task);

        $task->update([
            'status' => $task->status === Task::STATUS_DONE ? Task::STATUS_OPEN : Task::STATUS_DONE,
        ]);

        return back();
    }
}
