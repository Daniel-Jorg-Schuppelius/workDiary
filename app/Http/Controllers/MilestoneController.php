<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMilestoneRequest;
use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MilestoneController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('create', Milestone::class);

        return view('projects._milestone_dialog', [
            'project' => $project,
            'milestone' => null,
            'isDialog' => true,
        ]);
    }

    public function store(Project $project, SaveMilestoneRequest $request): RedirectResponse
    {
        Gate::authorize('create', Milestone::class);

        $data = $request->validated();

        $project->milestones()->create($data + [
            'created_by' => Auth::id(),
            'organization_id' => $project->organization_id,
        ]);

        return redirect()->route('projects.show', $project)
            ->with('success', __('Milestone angelegt.'));
    }

    public function edit(Project $project, Milestone $milestone): View
    {
        Gate::authorize('update', $milestone);

        return view('projects._milestone_dialog', [
            'project' => $project,
            'milestone' => $milestone,
            'isDialog' => true,
        ]);
    }

    public function update(Project $project, Milestone $milestone, SaveMilestoneRequest $request): RedirectResponse
    {
        Gate::authorize('update', $milestone);

        $milestone->update($request->validated());

        return redirect()->route('projects.show', $project)
            ->with('success', __('Milestone aktualisiert.'));
    }

    public function destroy(Project $project, Milestone $milestone): RedirectResponse
    {
        Gate::authorize('delete', $milestone);

        $milestone->delete();

        return redirect()->route('projects.show', $project)
            ->with('success', __('Milestone gelöscht.'));
    }
}
