<?php
/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetEntryController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Requests\SaveTimesheetEntryRequest;
use App\Models\{Project, TimeEntry, Timesheet};
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

class TimesheetEntryController extends Controller {
    public function create(Project $project, Timesheet $timesheet): View {
        Gate::authorize('update', $timesheet);
        $tasks = $project->tasks()->orderBy('title')->get(['id', 'title']);

        return view('timesheets._entry_form_dialog', compact('project', 'timesheet', 'tasks'));
    }

    public function store(Project $project, Timesheet $timesheet, SaveTimesheetEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timesheet);

        $data = $request->validated();
        $timesheet->entries()->create($data + [
            'user_id' => Auth::id(),
            'project_id' => $project->id,
            'organization_id' => $project->organization_id,
            'date' => $data['date'] ?? ($data['started_at'] ?? $timesheet->work_date),
            'kind' => $data['kind'] ?? TimeEntryKind::Work->value,
        ]);

        return back()->with('success', __('Zeile hinzugefügt.'));
    }

    public function update(Project $project, Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $entry->update($request->validated());

        return back()->with('success', __('Zeile aktualisiert.'));
    }

    public function destroy(Project $project, Timesheet $timesheet, TimeEntry $entry): RedirectResponse {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);

        $entry->delete();

        return back()->with('success', __('Zeile gelöscht.'));
    }
}
