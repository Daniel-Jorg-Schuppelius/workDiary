<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StopwatchController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Timesheet\TimesheetStatus;
use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Timesheet\Stopwatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StopwatchController extends Controller {
    public function __construct(protected Stopwatch $stopwatch) {
    }

    public function current(): View {
        return view('stopwatch._panel', [
            'current' => $this->stopwatch->current($this->authUser()),
        ]);
    }

    public function start(Request $request): RedirectResponse {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', 'exists:timesheets,id'],
        ]);

        $project = Project::findOrFail((int) $data['project_id']);

        // Existierenden Heute-Stundenzettel verwenden oder neu anlegen
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

        $this->stopwatch->start($this->authUser(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null);

        return back()->with('success', __('Stoppuhr gestartet.'));
    }

    public function stop(): RedirectResponse {
        $this->stopwatch->stop($this->authUser());

        return back()->with('success', __('Stoppuhr gestoppt.'));
    }
}
