<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTimeEntryRequest;
use App\Models\Project;
use App\Models\TimeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TimeEntryController extends Controller {
    public function create(Project $project): View {
        Gate::authorize('create', TimeEntry::class);

        $tasks = $project->tasks()
            ->where('status', '!=', \App\Models\Task::STATUS_DONE)
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('projects._time_entry_dialog', [
            'project'  => $project,
            'entry'    => null,
            'tasks'    => $tasks,
            'isDialog' => true,
        ]);
    }

    public function store(Project $project, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('create', TimeEntry::class);

        $data = $request->validated();

        $project->timeEntries()->create($data + [
            'user_id'         => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag erfasst.'));
    }

    public function edit(Project $project, TimeEntry $timeEntry): View {
        Gate::authorize('update', $timeEntry);

        $tasks = $project->tasks()
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('projects._time_entry_dialog', [
            'project'  => $project,
            'entry'    => $timeEntry,
            'tasks'    => $tasks,
            'isDialog' => true,
        ]);
    }

    public function update(Project $project, TimeEntry $timeEntry, SaveTimeEntryRequest $request): RedirectResponse {
        Gate::authorize('update', $timeEntry);

        $timeEntry->update($request->validated());

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag aktualisiert.'));
    }

    public function destroy(Project $project, TimeEntry $timeEntry): RedirectResponse {
        Gate::authorize('delete', $timeEntry);

        $timeEntry->delete();

        return redirect()->route('projects.show', ['project' => $project, '#' => 'time'])
            ->with('success', __('Zeiteintrag gelöscht.'));
    }
}
