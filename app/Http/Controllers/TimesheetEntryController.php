<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTimesheetEntryRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TimesheetEntryController extends Controller
{
    public function store(Project $project, Timesheet $timesheet, SaveTimesheetEntryRequest $request): RedirectResponse
    {
        Gate::authorize('update', $timesheet);

        $data = $request->validated();
        $timesheet->entries()->create($data + [
            'user_id' => Auth::id(),
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
            'date' => $data['date'] ?? ($data['started_at'] ?? $timesheet->work_date),
            'kind' => $data['kind'] ?? TimeEntry::KIND_WORK,
        ]);

        return back()->with('success', __('Zeile hinzugefügt.'));
    }

    public function update(Project $project, Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): RedirectResponse
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $entry->update($request->validated());

        return back()->with('success', __('Zeile aktualisiert.'));
    }

    public function destroy(Project $project, Timesheet $timesheet, TimeEntry $entry): RedirectResponse
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $entry->delete();

        return back()->with('success', __('Zeile gelöscht.'));
    }
}
