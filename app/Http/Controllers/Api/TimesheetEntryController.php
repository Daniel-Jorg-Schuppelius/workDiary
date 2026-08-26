<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTimesheetEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\{TimeEntry, Timesheet};
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\{Auth, Gate};
use OpenApi\Attributes as OA;

class TimesheetEntryController extends Controller {
    #[OA\Get(
        path: '/timesheets/{timesheet}/entries',
        summary: 'Zeiteinträge eines Stundenzettels',
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
    public function index(Timesheet $timesheet): AnonymousResourceCollection {
        Gate::authorize('view', $timesheet);

        return TimeEntryResource::collection($timesheet->entries()->get());
    }

    #[OA\Post(
        path: '/timesheets/{timesheet}/entries',
        summary: 'Zeiteintrag anlegen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab'))],
        requestBody: new OA\RequestBody(required: true, description: 'Entweder start_time/end_time (bzw. started_at/ended_at) oder minutes angeben', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'start_time', type: 'string', example: '08:00', nullable: true),
            new OA\Property(property: 'end_time', type: 'string', example: '16:30', nullable: true),
            new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'ended_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'minutes', type: 'integer', minimum: 0, maximum: 1440, nullable: true),
            new OA\Property(property: 'break_minutes', type: 'integer', minimum: 0, maximum: 480, nullable: true),
            new OA\Property(property: 'kind', type: 'string', enum: ['work', 'travel', 'standby'], nullable: true),
            new OA\Property(property: 'task_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: 500, nullable: true),
            new OA\Property(property: 'tag_ids', type: 'array', description: 'Tag-Sqids', items: new OA\Items(type: 'string', example: 'k7Qx2Ab')),
            new OA\Property(property: 'new_tags', type: 'string', maxLength: 500, nullable: true, description: 'Neue Tags, kommagetrennt'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function store(Timesheet $timesheet, SaveTimesheetEntryRequest $request): TimeEntryResource {
        Gate::authorize('update', $timesheet);
        $data = $request->validated();
        $entry = $timesheet->entries()->create($data + [
            'user_id' => Auth::id(),
            'project_id' => $timesheet->project_id,
            'organization_id' => $timesheet->organization_id,
            'date' => $data['date'] ?? ($data['started_at'] ?? $timesheet->work_date),
            'kind' => $data['kind'] ?? TimeEntryKind::Work->value,
        ]);

        return new TimeEntryResource($entry);
    }

    #[OA\Put(
        path: '/timesheets/{timesheet}/entries/{entry}',
        summary: 'Zeiteintrag aktualisieren',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [
            new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'entry', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
        ],
        requestBody: new OA\RequestBody(required: true, description: 'Entweder start_time/end_time (bzw. started_at/ended_at) oder minutes angeben', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'start_time', type: 'string', example: '08:00', nullable: true),
            new OA\Property(property: 'end_time', type: 'string', example: '16:30', nullable: true),
            new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'ended_at', type: 'string', format: 'date-time', nullable: true),
            new OA\Property(property: 'minutes', type: 'integer', minimum: 0, maximum: 1440, nullable: true),
            new OA\Property(property: 'break_minutes', type: 'integer', minimum: 0, maximum: 480, nullable: true),
            new OA\Property(property: 'kind', type: 'string', enum: ['work', 'travel', 'standby'], nullable: true),
            new OA\Property(property: 'task_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: 500, nullable: true),
            new OA\Property(property: 'tag_ids', type: 'array', description: 'Tag-Sqids', items: new OA\Items(type: 'string', example: 'k7Qx2Ab')),
            new OA\Property(property: 'new_tags', type: 'string', maxLength: 500, nullable: true, description: 'Neue Tags, kommagetrennt'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function update(Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): TimeEntryResource {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->update($request->validated());

        return new TimeEntryResource($entry);
    }

    #[OA\Delete(
        path: '/timesheets/{timesheet}/entries/{entry}',
        summary: 'Zeiteintrag löschen',
        tags: ['Timesheets'],
        security: [['bearerAuth' => ['timesheets:write']]],
        parameters: [
            new OA\Parameter(name: 'timesheet', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
            new OA\Parameter(name: 'entry', in: 'path', required: true, description: 'Sqid', schema: new OA\Schema(type: 'string', example: 'k7Qx2Ab')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ],
    )]
    public function destroy(Timesheet $timesheet, TimeEntry $entry): Response {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->delete();

        return response()->noContent();
    }
}
