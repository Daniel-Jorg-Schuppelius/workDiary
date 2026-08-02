<?php
/*
 * Created on   : Fri May 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProjectBillingRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\TimeEntry\TimeEntryKind;
use App\Http\Requests\{SaveProjectBillingRuleRequest, SaveProjectBillingSettingsRequest, SaveProjectRatesRequest};
use App\Models\{LexofficeArticle, Project, ProjectBillingRule};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProjectBillingRuleController extends Controller {
    public function create(Project $project, Request $request): View {
        $this->ensureBillingManager($request);

        return view('projects._billing_rule_form_dialog', [
            'project' => $project,
            'rule' => new ProjectBillingRule,
            'articles' => LexofficeArticle::active()->orderBy('name')->get(['external_id', 'name', 'unit_name', 'net_unit_price', 'vat_rate']),
            'kinds' => TimeEntryKind::options(),
            'itemTypes' => ProjectBillingRule::itemTypeOptions(),
        ]);
    }

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

    public function updateSettings(Project $project, SaveProjectBillingSettingsRequest $request): RedirectResponse {
        $this->ensureBillingManager($request);

        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Abrechnungs-Taktung gespeichert.'));
    }

    /** Projektstufe der Satzhierarchie (MVP-482); leer = erben. */
    public function updateRates(Project $project, SaveProjectRatesRequest $request): RedirectResponse {
        $this->ensureBillingManager($request);

        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Sätze gespeichert.'));
    }

    public function destroy(Project $project, ProjectBillingRule $billingRule): RedirectResponse {
        $this->ensureBillingManager(request());
        $this->ensureSameProject($project, $billingRule);

        $billingRule->delete();

        return redirect()
            ->route('projects.show', $project)
            ->with('success', __('Abrechnungs-Regel gelöscht.'));
    }

    private function ensureBillingManager(Request $request): void {
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
