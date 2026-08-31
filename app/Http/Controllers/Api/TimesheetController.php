<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Enums\Timesheet\TimesheetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\{Project, Timesheet};
use App\Services\Timesheet\{PdfRenderer, SignatureService};
use App\Support\Sqid;
use Illuminate\Http\{Request, Response};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class TimesheetController extends Controller {
    #[OA\Get(
        path: '/timesheets',
        summary: 'Stundenzettel auflisten',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:read']]],
        parameters: [
            new OA\Parameter(name: 'project', in: 'query', required: false, description: 'Projekt-Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 25)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection {
        Gate::authorize('viewAny', Timesheet::class);
        $query = Timesheet::query()->with(['user']);
        if (! ($request->user()?->isAdmin())) {
            $query->forUser((int) Auth::id());
        }
        if ($projectId = Sqid::decode(Project::class, $request->query('project'))) {
            $query->where('project_id', $projectId);
        }

        return TimesheetResource::collection($query->latest('work_date')->paginate($this->perPage($request)));
    }

    #[OA\Post(
        path: '/projects/{project}/timesheets',
        summary: 'Stundenzettel anlegen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'project', in: 'path', required: true, description: 'Projekt-Sqid oder kunde/projekt-Slug', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['work_date'], properties: [
            new OA\Property(property: 'work_date', type: 'string', format: 'date'),
            new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, nullable: true),
            new OA\Property(property: 'customer_role', type: 'string', maxLength: 255, nullable: true),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255, nullable: true),
            new OA\Property(property: 'notes', type: 'string', maxLength: 5000, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Project $project, SaveTimesheetRequest $request): TimesheetResource {
        Gate::authorize('create', Timesheet::class);
        $ts = $project->timesheets()->create($request->validated() + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
            'status' => TimesheetStatus::Draft->value,
        ]);

        return new TimesheetResource($ts);
    }

    #[OA\Get(
        path: '/timesheets/{timesheet}',
        summary: 'Stundenzettel anzeigen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:read']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function show(Timesheet $timesheet): TimesheetResource {
        Gate::authorize('view', $timesheet);

        return new TimesheetResource($timesheet->load(['entries', 'materialUsages', 'user']));
    }

    #[OA\Put(
        path: '/timesheets/{timesheet}',
        summary: 'Stundenzettel aktualisieren',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['work_date'], properties: [
            new OA\Property(property: 'work_date', type: 'string', format: 'date'),
            new OA\Property(property: 'customer_name', type: 'string', maxLength: 255, nullable: true),
            new OA\Property(property: 'customer_role', type: 'string', maxLength: 255, nullable: true),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255, nullable: true),
            new OA\Property(property: 'notes', type: 'string', maxLength: 5000, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Timesheet $timesheet, SaveTimesheetRequest $request): TimesheetResource {
        Gate::authorize('update', $timesheet);
        $timesheet->update($request->validated());

        return new TimesheetResource($timesheet->fresh(['entries', 'materialUsages']) ?? $timesheet);
    }

    #[OA\Delete(
        path: '/timesheets/{timesheet}',
        summary: 'Stundenzettel löschen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Timesheet $timesheet): Response {
        Gate::authorize('delete', $timesheet);
        $timesheet->delete();

        return response()->noContent();
    }

    #[OA\Post(
        path: '/timesheets/{timesheet}/submit',
        summary: 'Stundenzettel einreichen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function submit(Timesheet $timesheet): TimesheetResource {
        Gate::authorize('submit', $timesheet);
        $timesheet->update(['status' => TimesheetStatus::Submitted->value]);

        return new TimesheetResource($timesheet);
    }

    #[OA\Post(
        path: '/timesheets/{timesheet}/sign',
        summary: 'Stundenzettel unterschreiben',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['signature', 'customer_name'], properties: [
            new OA\Property(property: 'signature', type: 'string', description: 'Unterschrift (Data-URL)'),
            new OA\Property(property: 'customer_name', type: 'string', maxLength: 255),
            new OA\Property(property: 'customer_role', type: 'string', maxLength: 255, nullable: true),
            new OA\Property(property: 'customer_email', type: 'string', format: 'email', maxLength: 255, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function sign(Timesheet $timesheet, Request $request, SignatureService $svc): TimesheetResource {
        Gate::authorize('sign', $timesheet);
        $data = $request->validate([
            'signature' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);
        $svc->sign($timesheet, $data['signature'], $data, $request);

        return new TimesheetResource($timesheet->fresh() ?? $timesheet);
    }

    #[OA\Get(
        path: '/timesheets/{timesheet}/pdf',
        summary: 'Stundenzettel als PDF',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:read']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        responses: [
            new OA\Response(response: 200, description: 'PDF (application/pdf)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function pdf(Timesheet $timesheet, PdfRenderer $r): Response {
        Gate::authorize('view', $timesheet);

        return response($r->render($timesheet), 200, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Seitengröße klemmen (Sicherheitsscan 2026-08-23, S-58): `per_page` ging
     * ungeprüft an paginate() — ein Token konnte damit den gesamten Bestand in
     * einer Antwort anfordern.
     */
    private function perPage(Request $request): int {
        return max(1, min(100, (int) $request->input('per_page', 25)));
    }

}
