<?php
/*
 * Created on   : Tue May 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Task\TaskStatus;
use App\Http\Requests\SaveTaskRequest;
use App\Models\{Project, Task};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class TaskController extends Controller {
    public function create(Project $project, Request $request): View {
        Gate::authorize('create', Task::class);

        $milestones = $project->milestones()->orderBy('position')->orderBy('due_date')->get(['id', 'title']);
        $parentTasks = $project->tasks()->whereNull('parent_task_id')->orderBy('position')->get(['id', 'title']);
        $users = $this->assignableUsers($project);

        $preselectedParentId = $request->integer('parent_id') ?: null;

        return view('projects._task_dialog', [
            'project' => $project,
            'task' => null,
            'milestones' => $milestones,
            'parentTasks' => $parentTasks,
            'users' => $users,
            'assigneeIds' => [],
            'preselectedParentId' => $preselectedParentId,
            'isDialog' => true,
        ]);
    }

    public function store(Project $project, SaveTaskRequest $request): RedirectResponse {
        Gate::authorize('create', Task::class);

        $data = $request->validated();
        $assigneeIds = $data['assignee_ids'] ?? [];
        unset($data['assignee_ids']);

        $task = $project->tasks()->create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);
        $task->syncAssignees($assigneeIds);

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe angelegt.'));
    }

    public function edit(Project $project, Task $task): View {
        Gate::authorize('update', $task);

        $milestones = $project->milestones()->orderBy('position')->orderBy('due_date')->get(['id', 'title']);
        $parentTasks = $project->tasks()
            ->whereNull('parent_task_id')
            ->where('id', '!=', $task->id)
            ->orderBy('position')
            ->get(['id', 'title']);
        $users = $this->assignableUsers($project);

        return view('projects._task_dialog', [
            'project' => $project,
            'task' => $task,
            'milestones' => $milestones,
            'parentTasks' => $parentTasks,
            'users' => $users,
            'assigneeIds' => $task->assignees()->pluck('users.id')->all(),
            'preselectedParentId' => null,
            'isDialog' => true,
        ]);
    }

    public function update(Project $project, Task $task, SaveTaskRequest $request): RedirectResponse {
        Gate::authorize('update', $task);

        $data = $request->validated();
        $assigneeIds = $data['assignee_ids'] ?? null;
        unset($data['assignee_ids']);

        $task->update($data);
        if ($assigneeIds !== null) {
            $task->syncAssignees($assigneeIds);
        }

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe aktualisiert.'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse {
        Gate::authorize('delete', $task);

        $task->delete();

        return redirect()->route('projects.show', ['project' => $project, '#' => 'tasks'])
            ->with('success', __('Aufgabe gelöscht.'));
    }

    /**
     * Verschiebt/streckt eine Aufgabe im Zeitstrahl (Drag-&-Drop-Gantt):
     * setzt Start-/Enddatum. Liefert JSON für die clientseitige Aktualisierung.
     */
    public function schedule(Project $project, Task $task, Request $request): \Illuminate\Http\JsonResponse {
        Gate::authorize('update', $task);

        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $task->update([
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'start_date' => $task->start_date?->toDateString(),
            'due_date' => $task->due_date?->toDateString(),
        ]);
    }

    public function complete(Project $project, Task $task): RedirectResponse {
        Gate::authorize('update', $task);

        $task->update([
            'status' => $task->status === TaskStatus::Done ? TaskStatus::Open : TaskStatus::Done,
        ]);

        return back();
    }

    /**
     * Mögliche Bearbeiter einer Aufgabe: die dem Auftrag zugeordneten Personen
     * (Team-Mitglieder + Einzelmitglieder). Ist dem Projekt noch kein Team/
     * Mitglied zugeordnet, fällt die Auswahl auf alle Org-Benutzer zurück.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    private function assignableUsers(Project $project): \Illuminate\Support\Collection {
        $assignable = $project->assignableUsers();
        if ($assignable->isNotEmpty()) {
            return $assignable;
        }

        return $project->organization
            ? $project->organization->users()->orderBy('name')->get(['id', 'name'])->toBase()
            : collect();
    }
}
