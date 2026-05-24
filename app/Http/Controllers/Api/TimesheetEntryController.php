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

class TimesheetEntryController extends Controller {
    public function index(Timesheet $timesheet): AnonymousResourceCollection {
        Gate::authorize('view', $timesheet);

        return TimeEntryResource::collection($timesheet->entries()->get());
    }

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

    public function update(Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): TimeEntryResource {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->update($request->validated());

        return new TimeEntryResource($entry);
    }

    public function destroy(Timesheet $timesheet, TimeEntry $entry): Response {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->delete();

        return response()->noContent();
    }
}
