<?php

/*
 * Created on   : Tue May 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectRecurrenceRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Requests\SaveRecurrenceRuleRequest;
use App\Models\Customer;
use App\Models\EntryType;
use App\Models\Project;
use App\Models\RecurrenceRule;
use App\Models\User;
use App\Services\Recurrence\RecurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProjectRecurrenceRuleController extends Controller
{
    public function create(Project $project): View
    {
        Gate::authorize('update', $project);

        return view('projects._recurrence_rule_form_dialog', [
            'project' => $project,
            'rule' => new RecurrenceRule(),
            'entryTypes' => EntryType::query()->active()->ordered()->get(['id', 'label']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Project $project, SaveRecurrenceRuleRequest $request): RedirectResponse
    {
        Gate::authorize('update', $project);

        $data = $request->validated();
        $data['project_id'] = $project->id;
        $data['organization_id'] = $project->organization_id;
        $data['created_by'] = Auth::id();

        RecurrenceRule::create($data);

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'recurrence'])
            ->with('success', __('Wiederkehr-Regel angelegt.'));
    }

    public function edit(Project $project, RecurrenceRule $recurrenceRule): View
    {
        Gate::authorize('update', $project);
        $this->ensureSameProject($project, $recurrenceRule);

        return view('projects._recurrence_rule_form_dialog', [
            'project' => $project,
            'rule' => $recurrenceRule,
            'entryTypes' => EntryType::query()->active()->ordered()->get(['id', 'label']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(
        Project $project,
        RecurrenceRule $recurrenceRule,
        SaveRecurrenceRuleRequest $request,
    ): RedirectResponse {
        Gate::authorize('update', $project);
        $this->ensureSameProject($project, $recurrenceRule);

        $recurrenceRule->update($request->validated());

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'recurrence'])
            ->with('success', __('Wiederkehr-Regel aktualisiert.'));
    }

    public function destroy(Project $project, RecurrenceRule $recurrenceRule): RedirectResponse
    {
        Gate::authorize('update', $project);
        $this->ensureSameProject($project, $recurrenceRule);

        $recurrenceRule->delete();

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'recurrence'])
            ->with('success', __('Wiederkehr-Regel gelöscht.'));
    }

    /**
     * Manueller Trigger des Generators für diese Regel — nützlich, wenn der
     * User direkt sehen will, was die Regel produziert.
     */
    public function run(
        Project $project,
        RecurrenceRule $recurrenceRule,
        RecurrenceGenerator $generator,
    ): RedirectResponse {
        Gate::authorize('update', $project);
        $this->ensureSameProject($project, $recurrenceRule);

        $created = $generator->generateForRule($recurrenceRule->fresh(), CarbonImmutable::now(), 28);

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => 'recurrence'])
            ->with('success', __(':count Aufträge erzeugt.', ['count' => $created]));
    }

    private function ensureSameProject(Project $project, RecurrenceRule $rule): void
    {
        if ((int) $rule->project_id !== (int) $project->id) {
            throw new AccessDeniedHttpException;
        }
    }
}
