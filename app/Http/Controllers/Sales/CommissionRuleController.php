<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CommissionRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Enums\Sales\{CommissionScope, LeadSource};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCommissionRuleRequest;
use App\Models\{Article, User};
use App\Models\Sales\CommissionRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\{Auth, Gate};
use Illuminate\View\View;

/**
 * Provisionsregeln je Organisation (Feature 146, MVP-729): Satz je Lead-Quelle,
 * Produktgruppe oder Vertriebsperson mit Gueltigkeitszeitraum und Prioritaet.
 *
 * Bewusst reine Stammdatenpflege — gerechnet wird nichts hier, sondern am
 * bezahlten Beleg ({@see \App\Services\Sales\CommissionAccrualService}).
 */
class CommissionRuleController extends Controller {
    use ResolvesCurrentOrganization;

    public function index(): View {
        Gate::authorize('viewAny', CommissionRule::class);

        return view('sales.commission-rules.index', [
            'rules' => CommissionRule::query()
                ->with('user:id,name')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString(),
            'canManage' => Gate::allows('create', CommissionRule::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', CommissionRule::class);

        return view('sales.commission-rules._form_dialog', $this->formData(null));
    }

    public function store(SaveCommissionRuleRequest $request): RedirectResponse {
        Gate::authorize('create', CommissionRule::class);

        CommissionRule::create($this->attributes($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('commission-rules.index')->with('success', __('commission.flash.rule_created'));
    }

    public function edit(CommissionRule $rule): View {
        Gate::authorize('update', $rule);

        return view('sales.commission-rules._form_dialog', $this->formData($rule));
    }

    public function update(SaveCommissionRuleRequest $request, CommissionRule $rule): RedirectResponse {
        Gate::authorize('update', $rule);

        $rule->fill($this->attributes($request));
        $rule->save();

        return redirect()->route('commission-rules.index')->with('success', __('commission.flash.rule_updated'));
    }

    public function destroy(CommissionRule $rule): RedirectResponse {
        Gate::authorize('delete', $rule);

        $rule->delete();

        return redirect()->route('commission-rules.index')->with('success', __('commission.flash.rule_deleted'));
    }

    /**
     * Gemeinsame Abbildung Formular → Spalten. Felder ausserhalb des
     * gewaehlten Geltungsbereichs werden geleert, damit keine
     * widerspruechliche Regel entsteht („scope=all mit user_id").
     *
     * @return array<string, mixed>
     */
    private function attributes(SaveCommissionRuleRequest $request): array {
        $data = $request->validated();
        $scope = CommissionScope::from((string) $data['scope']);

        return [
            'name' => (string) $data['name'],
            'scope' => $scope,
            'scope_value' => $scope->needsValue() ? (string) $data['scope_value'] : null,
            'user_id' => $scope === CommissionScope::User ? (int) $data['user_id'] : null,
            'rate_percent' => (string) $data['rate_percent'],
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'priority' => (int) $data['priority'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }

    /**
     * Auswahllisten des Dialogs. Die Produktgruppen kommen aus dem
     * Artikelstamm (`articles.category`) — WorkDiary fuehrt keinen zweiten
     * Gruppenbegriff nur fuer Provisionen.
     *
     * @return array<string, mixed>
     */
    private function formData(?CommissionRule $rule): array {
        return [
            'rule' => $rule,
            'users' => User::query()
                ->where('organization_id', $this->currentOrganization()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'leadSources' => LeadSource::cases(),
            'productGroups' => Article::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->filter(static fn (?string $c): bool => $c !== null && $c !== '')
                ->values()
                ->all(),
        ];
    }
}
