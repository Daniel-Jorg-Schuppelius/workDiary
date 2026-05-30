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
use Illuminate\Support\Facades\{Auth, Gate};

class StopwatchController extends Controller {
    public function __construct(protected Stopwatch $stopwatch) {}

    public function current(): JsonResponse|TimeEntryResource {
        $entry = $this->stopwatch->current($this->authUser());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }

    public function start(Request $request): TimeEntryResource {
        $request->merge([
            'project_id' => Sqid::decode(Project::class, $request->input('project_id')),
            'task_id' => Sqid::decode(\App\Models\Task::class, $request->input('task_id')),
            'timesheet_id' => Sqid::decode(Timesheet::class, $request->input('timesheet_id')),
        ]);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', 'exists:timesheets,id'],
        ]);

        $project = Project::findOrFail((int) $data['project_id']);
        $today = CarbonImmutable::today();
        $timesheet = isset($data['timesheet_id'])
            ? Timesheet::findOrFail((int) $data['timesheet_id'])
            : Timesheet::firstOrCreate([
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'work_date' => $today,
            ], [
                'organization_id' => $project->organization_id,
                'status' => TimesheetStatus::Draft->value,
            ]);
        Gate::authorize('update', $timesheet);

        return new TimeEntryResource($this->stopwatch->start($this->authUser(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null));
    }

    public function stop(): JsonResponse|TimeEntryResource {
        $entry = $this->stopwatch->stop($this->authUser());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }
}
