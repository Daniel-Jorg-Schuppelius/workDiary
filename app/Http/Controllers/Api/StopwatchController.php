<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Enums\Timesheet\TimesheetStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\{Project, Timesheet};
use App\Services\Timesheet\Stopwatch;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use OpenApi\Attributes as OA;

class StopwatchController extends Controller {
    public function __construct(protected Stopwatch $stopwatch) {}

    #[OA\Get(
        path: '/stopwatch',
        summary: 'Laufende Stoppuhr',
        tags: ['Stopwatch'],
        security: [['bearerAuth' => ['stopwatch:read']]],
        responses: [
            new OA\Response(response: 200, description: 'OK (null, wenn keine Stoppuhr läuft)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function current(): JsonResponse|TimeEntryResource {
        $entry = $this->stopwatch->current($this->authUser());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }

    #[OA\Post(
        path: '/stopwatch/start',
        summary: 'Stoppuhr starten',
        tags: ['Stopwatch'],
        security: [['bearerAuth' => ['stopwatch:write']]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['project_id'], properties: [
            new OA\Property(property: 'project_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab'),
            new OA\Property(property: 'task_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'diary_entry_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'timesheet_id', type: 'string', description: 'Sqid', example: 'k7Qx2Ab', nullable: true),
            new OA\Property(property: 'description', type: 'string', maxLength: 500, nullable: true),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function start(Request $request): TimeEntryResource {
        $request->merge([
            'project_id' => Sqid::decode(Project::class, $request->input('project_id')),
            'task_id' => Sqid::decode(\App\Models\Task::class, $request->input('task_id')),
            'diary_entry_id' => Sqid::decode(\App\Models\DiaryEntry::class, $request->input('diary_entry_id')),
            'timesheet_id' => Sqid::decode(Timesheet::class, $request->input('timesheet_id')),
        ]);

        $data = $request->validate([
            'project_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'task_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('tasks')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('timesheets')],
        ]);

        $project = Project::findOrFail((int) $data['project_id']);
        $today = CarbonImmutable::today();
        $timesheet = isset($data['timesheet_id'])
            ? Timesheet::findOrFail((int) $data['timesheet_id'])
            : DB::transaction(function () use ($project, $today) {
                // Per-User serialisieren (kein Unique-Index als Backstop) —
                // sonst zwei Stundenzettel je Tag bei parallelen Starts.
                \App\Models\User::query()->whereKey(Auth::id())->lockForUpdate()->first();

                return Timesheet::firstOrCreate([
                    'project_id' => $project->id,
                    'user_id' => Auth::id(),
                    'work_date' => $today,
                ], [
                    'organization_id' => $project->organization_id,
                    'status' => TimesheetStatus::Draft->value,
                ]);
            });
        Gate::authorize('update', $timesheet);

        return new TimeEntryResource($this->stopwatch->start($this->authUser(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null, $data['diary_entry_id'] ?? null));
    }

    #[OA\Post(
        path: '/stopwatch/stop',
        summary: 'Stoppuhr stoppen',
        tags: ['Stopwatch'],
        security: [['bearerAuth' => ['stopwatch:write']]],
        responses: [
            new OA\Response(response: 200, description: 'OK (null, wenn keine Stoppuhr lief)'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ],
    )]
    public function stop(): JsonResponse|TimeEntryResource {
        $entry = $this->stopwatch->stop($this->authUser());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }
}
