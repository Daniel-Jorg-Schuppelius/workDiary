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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller {
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

    public function store(SaveProjectRequest $request): ProjectResource {
        Gate::authorize('create', Project::class);
        $project = Project::create($request->validated() + [
            'created_by' => Auth::id(),
            'organization_id' => $request->user()?->organization_id,
        ]);

        return new ProjectResource($project);
    }

    public function show(Project $project): ProjectResource {
        Gate::authorize('view', $project);

        return new ProjectResource($project);
    }

    public function update(Project $project, SaveProjectRequest $request): ProjectResource {
        Gate::authorize('update', $project);
        $project->update($request->validated());

        return new ProjectResource($project->fresh() ?? $project);
    }

    public function destroy(Project $project): Response {
        Gate::authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }
}
