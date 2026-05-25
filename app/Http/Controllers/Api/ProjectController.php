<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\{Request, Response};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class ProjectController extends Controller {
    #[OA\Get(
        path: '/projects',
        summary: 'Projekte auflisten',
        tags: ['Projects'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'customer', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'archived', in: 'query', schema: new OA\Schema(type: 'boolean')),
        ],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Project::class);
        $query = Project::query();
        if ($customerId = $request->integer('customer')) {
            $query->where('customer_id', $customerId);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($request->boolean('archived') === false) {
            $query->whereNull('archived_at');
        }
        if ($search = $request->string('search')->toString()) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        return ProjectResource::collection($query->orderBy('name')->paginate((int) $request->input('per_page', 25)));
    }

    #[OA\Post(
        path: '/projects',
        summary: 'Projekt anlegen',
        tags: ['Projects'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 201, description: 'Created')],
    )]
    public function store(SaveProjectRequest $request): ProjectResource {
        Gate::authorize('create', Project::class);
        $project = Project::create($request->validated() + [
            'created_by' => Auth::id(),
            'organization_id' => $request->user()?->organization_id,
        ]);

        return new ProjectResource($project);
    }

    #[OA\Get(
        path: '/projects/{project}',
        summary: 'Projekt anzeigen',
        tags: ['Projects'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function show(Project $project): ProjectResource {
        Gate::authorize('view', $project);

        return new ProjectResource($project);
    }

    #[OA\Put(
        path: '/projects/{project}',
        summary: 'Projekt aktualisieren',
        tags: ['Projects'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'OK')],
    )]
    public function update(Project $project, SaveProjectRequest $request): ProjectResource {
        Gate::authorize('update', $project);
        $project->update($request->validated());

        return new ProjectResource($project->fresh() ?? $project);
    }

    #[OA\Delete(
        path: '/projects/{project}',
        summary: 'Projekt löschen',
        tags: ['Projects'],
        security: [['bearerAuth' => []]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'No Content')],
    )]
    public function destroy(Project $project): Response {
        Gate::authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }
}
