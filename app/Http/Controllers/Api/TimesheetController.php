<?php

/*
 * Created on   : Thu May 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimesheetController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveTimesheetRequest;
use App\Http\Resources\TimesheetResource;
use App\Models\Project;
use App\Models\Timesheet;
use App\Services\Timesheet\PdfRenderer;
use App\Services\Timesheet\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Enums\Timesheet\TimesheetStatus;

class TimesheetController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Timesheet::class);
        $query = Timesheet::query()->with(['user']);
        if (! ($request->user()?->isAdmin())) {
            $query->forUser((int) Auth::id());
        }
        if ($projectId = $request->integer('project')) {
            $query->where('project_id', $projectId);
        }

        return TimesheetResource::collection($query->latest('work_date')->paginate((int) $request->input('per_page', 25)));
    }

    public function store(Project $project, SaveTimesheetRequest $request): TimesheetResource
    {
        Gate::authorize('create', Timesheet::class);
        $ts = $project->timesheets()->create($request->validated() + [
            'user_id' => Auth::id(),
            'organization_id' => $project->organization_id,
            'status' => TimesheetStatus::Draft->value,
        ]);

        return new TimesheetResource($ts);
    }

    public function show(Timesheet $timesheet): TimesheetResource
    {
        Gate::authorize('view', $timesheet);

        return new TimesheetResource($timesheet->load(['entries', 'materialUsages', 'user']));
    }

    public function update(Timesheet $timesheet, SaveTimesheetRequest $request): TimesheetResource
    {
        Gate::authorize('update', $timesheet);
        $timesheet->update($request->validated());

        return new TimesheetResource($timesheet->fresh(['entries', 'materialUsages']) ?? $timesheet);
    }

    public function destroy(Timesheet $timesheet): Response
    {
        Gate::authorize('delete', $timesheet);
        $timesheet->delete();

        return response()->noContent();
    }

    public function submit(Timesheet $timesheet): TimesheetResource
    {
        Gate::authorize('submit', $timesheet);
        $timesheet->update(['status' => TimesheetStatus::Submitted->value]);

        return new TimesheetResource($timesheet);
    }

    public function sign(Timesheet $timesheet, Request $request, SignatureService $svc): TimesheetResource
    {
        Gate::authorize('sign', $timesheet);
        $data = $request->validate([
            'signature' => ['required', 'string'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_role' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
        ]);
        $svc->sign($timesheet, $data['signature'], $data, $request);

        return new TimesheetResource($timesheet->fresh() ?? $timesheet);
    }

    public function pdf(Timesheet $timesheet, PdfRenderer $r): Response
    {
        Gate::authorize('view', $timesheet);

        return response($r->render($timesheet), 200, ['Content-Type' => 'application/pdf']);
    }
}
