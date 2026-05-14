<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTimesheetEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\TimeEntry;
use App\Models\Timesheet;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TimesheetEntryController extends Controller
{
    public function index(Timesheet $timesheet): AnonymousResourceCollection
    {
        Gate::authorize('view', $timesheet);

        return TimeEntryResource::collection($timesheet->entries()->get());
    }

    public function store(Timesheet $timesheet, SaveTimesheetEntryRequest $request): TimeEntryResource
    {
        Gate::authorize('update', $timesheet);
        $data = $request->validated();
        $entry = $timesheet->entries()->create($data + [
            'user_id' => Auth::id(),
            'project_id' => $timesheet->project_id,
            'organization_id' => $timesheet->organization_id,
            'date' => $data['date'] ?? ($data['started_at'] ?? $timesheet->work_date),
            'kind' => $data['kind'] ?? TimeEntry::KIND_WORK,
        ]);

        return new TimeEntryResource($entry);
    }

    public function update(Timesheet $timesheet, TimeEntry $entry, SaveTimesheetEntryRequest $request): TimeEntryResource
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->update($request->validated());

        return new TimeEntryResource($entry);
    }

    public function destroy(Timesheet $timesheet, TimeEntry $entry): Response
    {
        Gate::authorize('update', $timesheet);
        abort_unless((int) $entry->timesheet_id === (int) $timesheet->id, 404);
        $entry->delete();

        return response()->noContent();
    }
}
