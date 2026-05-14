<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Timesheet\Stopwatch;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StopwatchController extends Controller
{
    public function __construct(protected Stopwatch $stopwatch) {}

    public function current(): View
    {
        $user = Auth::user();

        return view('stopwatch._panel', [
            'current' => $user ? $this->stopwatch->current($user) : null,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'task_id' => ['nullable', 'integer', 'exists:tasks,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'timesheet_id' => ['nullable', 'integer', 'exists:timesheets,id'],
        ]);

        /** @var Project $project */
        $project = Project::findOrFail($data['project_id']);

        // Existierenden Heute-Stundenzettel verwenden oder neu anlegen
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

        $this->stopwatch->start(Auth::user(), $timesheet, $data['task_id'] ?? null, $data['description'] ?? null);

        return back()->with('success', __('Stoppuhr gestartet.'));
    }

    public function stop(): RedirectResponse
    {
        $this->stopwatch->stop(Auth::user());

        return back()->with('success', __('Stoppuhr gestoppt.'));
    }
}
