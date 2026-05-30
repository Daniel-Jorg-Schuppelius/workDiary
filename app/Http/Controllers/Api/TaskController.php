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
use App\Models\{Project, Task};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class TaskController extends Controller {
    #[OA\Get(
        path: '/tasks',
        summary: 'Tasks auflisten',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'project', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'assigned_to', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Task::class);
        $query = Task::query();
        if ($projectId = Sqid::decode(Project::class, $request->query('project'))) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($assignedTo = Sqid::decode(\App\Models\User::class, $request->query('assigned_to'))) {
            $query->where('assigned_to', $assignedTo);
        }

        return TaskResource::collection($query->orderBy('position')->orderBy('id')->paginate((int) $request->input('per_page', 25)));
    }

    #[OA\Post(
        path: '/projects/{project}/tasks',
        summary: 'Task im Projekt anlegen',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 201, description: 'Created')],
    )]
    public function store(Project $project, SaveTaskRequest $request): TaskResource {
        Gate::authorize('create', Task::class);
        $task = $project->tasks()->create($request->validated() + [
            'created_by' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return new TaskResource($task);
    }

    #[OA\Get(
        path: '/tasks/{task}',
        summary: 'Task anzeigen',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(Task $task): TaskResource {
        Gate::authorize('view', $task);

        return new TaskResource($task);
    }

    #[OA\Put(
        path: '/tasks/{task}',
        summary: 'Task aktualisieren',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Task $task, SaveTaskRequest $request): TaskResource {
        Gate::authorize('update', $task);
        $task->update($request->validated());

        return new TaskResource($task->fresh() ?? $task);
    }

    #[OA\Delete(
        path: '/tasks/{task}',
        summary: 'Task löschen',
        tags: ['Tasks'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'No Content')],
    )]
    public function destroy(Task $task): Response {
        Gate::authorize('delete', $task);
        $task->delete();

        return response()->noContent();
    }
}
