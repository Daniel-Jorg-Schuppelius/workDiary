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

use App\Http\Controllers\Controller;
use App\Http\Resources\TimeEntryResource;
use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Timesheet\Stopwatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class StopwatchController extends Controller
{
    public function __construct(protected Stopwatch $stopwatch) {}

    public function current(): JsonResponse|TimeEntryResource
    {
        $entry = $this->stopwatch->current(Auth::user());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }

    public function start(Request $request): TimeEntryResource
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', 'exists:timesheets,id'],
        ]);

        $project = Project::findOrFail($data['project_id']);
        $today = CarbonImmutable::today();
        $timesheet = isset($data['timesheet_id'])
            ? Timesheet::findOrFail($data['timesheet_id'])
            : Timesheet::firstOrCreate([
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'work_date' => $today,
            ], [
                'organization_id' => $project->organization_id,
                'status' => Timesheet::STATUS_DRAFT,
            ]);
        Gate::authorize('update', $timesheet);

        return new TimeEntryResource($this->stopwatch->start(Auth::user(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null));
    }

    public function stop(): JsonResponse|TimeEntryResource
    {
        $entry = $this->stopwatch->stop(Auth::user());

        return $entry ? new TimeEntryResource($entry) : response()->json(null);
    }
}
