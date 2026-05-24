<?php
/*
 * Created on   : Sun May 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutomationRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AutomationRule, AutomationRuleRun, User};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Verwaltung der {@see AutomationRule}-Definitionen einer Organisation.
 *
 * MVP-Scope: Liste, Toggle (aktiv/inaktiv), Audit-View pro Regel.
 * Erstellung/Editierung erfolgt aktuell roh als JSON, ein visueller
 * Form-Builder ist als Phase-2-Erweiterung vorgesehen.
 */
class AutomationRuleController extends Controller {
    public function index(): View {
        $this->ensureAdmin();

        $rules = AutomationRule::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return view('admin.automations.index', [
            'rules' => $rules,
        ]);
    }

    public function show(AutomationRule $automationRule): View {
        $this->ensureAdmin();
        $this->ensureOwnsRule($automationRule);

        $runs = AutomationRuleRun::query()
            ->where('rule_id', $automationRule->id)
            ->orderByDesc('ran_at')
            ->limit(50)
            ->get();

        return view('admin.automations.show', [
            'rule' => $automationRule,
            'runs' => $runs,
        ]);
    }

    public function toggle(AutomationRule $automationRule): RedirectResponse {
        $this->ensureAdmin();
        $this->ensureOwnsRule($automationRule);

        $automationRule->is_active = ! $automationRule->is_active;
        $automationRule->save();

        return redirect()
            ->route('admin.automations.index')
            ->with('status', __('Regel aktualisiert.'));
    }

    public function store(Request $request): RedirectResponse {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => 'required|string|max:64',
            'conditions' => 'required|string',
            'actions' => 'required|string',
            'priority' => 'nullable|integer|min:1|max:9999',
        ]);

        $conditions = $this->decodeJson($data['conditions']);
        $actions = $this->decodeJson($data['actions']);
        abort_if($conditions === null || $actions === null, 422, 'Ungültiges JSON');

        AutomationRule::create([
            'organization_id' => (int) app('currentOrganization')->id,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'conditions' => $conditions,
            'actions' => $actions,
            'is_active' => true,
            'priority' => (int) ($data['priority'] ?? 100),
            'created_by_id' => Auth::id(),
        ]);

        return redirect()
            ->route('admin.automations.index')
            ->with('status', __('Regel angelegt.'));
    }

    public function destroy(AutomationRule $automationRule): RedirectResponse {
        $this->ensureAdmin();
        $this->ensureOwnsRule($automationRule);

        $automationRule->delete();

        return redirect()
            ->route('admin.automations.index')
            ->with('status', __('Regel gelöscht.'));
    }

    private function ensureAdmin(): void {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);
    }

    private function ensureOwnsRule(AutomationRule $rule): void {
        $orgId = (int) (app('currentOrganization')?->id ?? 0);
        abort_unless($rule->organization_id === $orgId, 404);
    }

    private function decodeJson(string $raw): mixed {
        try {
            return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
