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

use App\Enums\Sales\{CommissionScope, CommissionTierPeriod, LeadSource};
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaveCommissionRuleRequest;
use App\Models\Article\Article;
use App\Models\Platform\User;
use App\Models\Sales\{CommissionAgent, CommissionRule};
use CommonToolkit\Enums\CurrencyCode;
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

        $rule = CommissionRule::create($this->attributes($request) + [
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => Auth::id(),
        ]);
        $this->syncTiers($rule, $request);

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
        $this->syncTiers($rule, $request);

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
            'commission_agent_id' => $scope === CommissionScope::Agent ? (int) $data['commission_agent_id'] : null,
            'rate_percent' => (string) $data['rate_percent'],
            // Schwellen und Deckel gelten in der Standardwährung der Rechnungen.
            'currency' => CurrencyCode::tryFrom(strtoupper((string) config('invoicing.default_currency', 'EUR'))) ?? CurrencyCode::Euro,
            'tier_period' => isset($data['tier_period']) && $data['tier_period'] !== '' ? CommissionTierPeriod::from((string) $data['tier_period']) : null,
            'annual_cap_amount' => isset($data['annual_cap_amount']) ? (string) $data['annual_cap_amount'] : null,
            'liability_days' => isset($data['liability_days']) ? (int) $data['liability_days'] : null,
            'is_partial_accrual' => (bool) ($data['is_partial_accrual'] ?? false),
            'valid_from' => $data['valid_from'] ?? null,
            'valid_to' => $data['valid_to'] ?? null,
            'priority' => (int) $data['priority'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'note' => $data['note'] ?? null,
        ];
    }

    /** Staffelstufen ersetzen: nur vollständige Zeilen, nur mit Staffelzeitraum. */
    private function syncTiers(CommissionRule $rule, SaveCommissionRuleRequest $request): void {
        $rule->tiers()->delete();
        if ($rule->tier_period === null) {
            return;
        }
        $seen = [];
        foreach ((array) $request->validated('tiers', []) as $row) {
            $threshold = is_array($row) ? ($row['threshold'] ?? null) : null;
            $rate = is_array($row) ? ($row['rate'] ?? null) : null;
            if ($threshold === null || $threshold === '' || $rate === null || $rate === '' || isset($seen[(string) $threshold])) {
                continue;
            }
            $seen[(string) $threshold] = true;
            $rule->tiers()->create(['organization_id' => $rule->organization_id, 'threshold_amount' => (string) $threshold, 'rate_percent' => (string) $rate]);
        }
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
            'agents' => CommissionAgent::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'company']),
            'tiers' => $rule?->orderedTiers() ?? collect(),
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
