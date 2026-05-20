<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller {
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Task::class);
        $query = Task::query();
        if ($projectId = $request->integer('project')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($assignedTo = $request->integer('assigned_to')) {
            $query->where('assigned_to', $assignedTo);
        }

        return TaskResource::collection($query->orderBy('position')->orderBy('id')->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Project $project, SaveTaskRequest $request): TaskResource {
        Gate::authorize('create', Task::class);
        $task = $project->tasks()->create($request->validated() + [
            'created_by' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return new TaskResource($task);
    }

    public function show(Task $task): TaskResource {
        Gate::authorize('view', $task);

        return new TaskResource($task);
    }

    public function update(Task $task, SaveTaskRequest $request): TaskResource {
        Gate::authorize('update', $task);
        $task->update($request->validated());

        return new TaskResource($task->fresh() ?? $task);
    }

    public function destroy(Task $task): Response {
        Gate::authorize('delete', $task);
        $task->delete();

        return response()->noContent();
    }
}
