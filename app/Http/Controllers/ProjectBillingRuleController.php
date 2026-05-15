<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProjectBillingRuleRequest;
use App\Models\Project;
use App\Models\ProjectBillingRule;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProjectBillingRuleController extends Controller {
    public function store(Project $project, SaveProjectBillingRuleRequest $request): RedirectResponse {
        $this->ensureBillingManager($request);

        $data = $request->validated();
        $data['plugin_id'] = $data['plugin_id'] ?? 'lexoffice';
        $data['item_type'] = $data['item_type'] ?? 'service';
        $data['priority'] = $data['priority'] ?? 0;
        $data['organization_id'] = $project->organization_id;

        $project->billingRules()->create($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Abrechnungs-Regel angelegt.'));
    }

    public function update(
        Project $project,
        ProjectBillingRule $billingRule,
        SaveProjectBillingRuleRequest $request,
    ): RedirectResponse {
        $this->ensureBillingManager($request);
        $this->ensureSameProject($project, $billingRule);

        $data = $request->validated();
        $data['plugin_id'] = $data['plugin_id'] ?? $billingRule->plugin_id;
        $data['item_type'] = $data['item_type'] ?? $billingRule->item_type;
        $data['priority'] = $data['priority'] ?? $billingRule->priority;

        $billingRule->update($data);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Abrechnungs-Regel aktualisiert.'));
    }

    public function destroy(Project $project, ProjectBillingRule $billingRule): RedirectResponse {
        $this->ensureBillingManager(request());
        $this->ensureSameProject($project, $billingRule);

        $billingRule->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Abrechnungs-Regel gelöscht.'));
    }

    private function ensureBillingManager(\Illuminate\Http\Request $request): void {
        if ($request->user()?->canManageBilling() !== true) {
            throw new AccessDeniedHttpException;
        }
    }

    private function ensureSameProject(Project $project, ProjectBillingRule $billingRule): void {
        if ((int) $billingRule->project_id !== (int) $project->id) {
            throw new AccessDeniedHttpException;
        }
    }
}
