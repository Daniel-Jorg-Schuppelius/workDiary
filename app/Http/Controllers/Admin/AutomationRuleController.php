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

use App\Automation\Actions\RuleAction;
use App\Automation\RuleEngine;
use App\Automation\Triggers\RuleTrigger;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Automation\{AutomationRule, AutomationRuleRun};
use App\Models\Platform\User;
use CommonToolkit\Helper\Data\JsonHelper;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\{Rule, ValidationException};
use Illuminate\View\View;

/**
 * Verwaltung der {@see AutomationRule}-Definitionen einer Organisation.
 *
 * MVP-Scope: Liste, Toggle (aktiv/inaktiv), Audit-View pro Regel.
 * Erstellung/Editierung erfolgt aktuell roh als JSON, ein visueller
 * Form-Builder ist als Phase-2-Erweiterung vorgesehen.
 */
class AutomationRuleController extends Controller {
    use ResolvesCurrentOrganization;
    public function index(): View {
        $this->ensureAdmin();

        $rules = AutomationRule::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        $engine = app(RuleEngine::class);

        return view('admin.automations.index', [
            'rules' => $rules,
            'triggerLabels' => collect($engine->triggers())->mapWithKeys(static fn (RuleTrigger $t): array => [$t->key() => $t->label()])->all(),
            'actionLabels' => collect($engine->actions())->mapWithKeys(static fn (RuleAction $a): array => [$a->type() => $a->label()])->all(),
        ]);
    }

    /** Dialog-Fragment für <x-modal> (data-entry-modal-trigger). */
    public function create(): View {
        $this->ensureAdmin();

        $engine = app(RuleEngine::class);

        return view('admin.automations._form_dialog', [
            'triggers' => $engine->triggers(),
            'actions' => $engine->actions(),
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

        $engine = app(RuleEngine::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'trigger_event' => ['required', 'string', Rule::in(array_map(static fn (RuleTrigger $t): string => $t->key(), $engine->triggers()))],
            'conditions' => 'required|string',
            'action_type' => ['required', 'string', Rule::in(array_map(static fn (RuleAction $a): string => $a->type(), $engine->actions()))],
            'priority' => 'nullable|integer|min:1|max:9999',
        ]);

        $conditions = $this->decodeJson($data['conditions']);
        if (! is_array($conditions)) {
            throw ValidationException::withMessages(['conditions' => __('automation.error.invalid_json')]);
        }
        $action = collect($engine->actions())->first(static fn (RuleAction $a): bool => $a->type() === $data['action_type']);
        if (! $action instanceof RuleAction || ! in_array($data['trigger_event'], $action->triggers(), true)) {
            throw ValidationException::withMessages(['action_type' => __('automation.error.action_trigger_mismatch')]);
        }
        $actions = [['type' => $action->type(), 'params' => []]];

        AutomationRule::create([
            'organization_id' => (int) $this->currentOrganization()->id,
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
        $orgId = (int) $this->currentOrganization()->id;
        abort_unless($rule->organization_id === $orgId, 404);
    }

    private function decodeJson(string $raw): mixed {
        try {
            return JsonHelper::decode($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
