<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SurchargeRuleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Enums\Surcharge\SurchargeKind;
use App\Http\Controllers\Controller;
use App\Models\Surcharge\SurchargeRule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Admin-UI für Zuschlagsregeln (Feature 005): Listenseite + Modal-CRUD
 * analog admin/notification-rules bzw. admin/per-diem-rates.
 * Pflege durch Admin und Buchhaltung/Lohnbüro (surchargeRule.manage).
 */
class SurchargeRuleController extends Controller {
    public function index(): View {
        Gate::authorize('viewAny', SurchargeRule::class);

        $rules = SurchargeRule::query()
            ->orderBy('kind')
            ->orderByDesc('percentage')
            ->orderBy('code')
            ->get();

        return view('admin.surcharge-rules.index', [
            'rules' => $rules,
            'canManage' => Gate::allows('create', SurchargeRule::class),
        ]);
    }

    public function create(): View {
        Gate::authorize('create', SurchargeRule::class);

        return view('admin.surcharge-rules._form_dialog', [
            'rule' => new SurchargeRule(['kind' => SurchargeKind::Night->value, 'priority' => 0, 'active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse {
        Gate::authorize('create', SurchargeRule::class);

        SurchargeRule::query()->create($this->validated($request));

        return redirect()->route('admin.surcharge-rules.index')
            ->with('success', __('surcharge.flash.created'));
    }

    public function edit(SurchargeRule $surchargeRule): View {
        Gate::authorize('update', $surchargeRule);

        return view('admin.surcharge-rules._form_dialog', [
            'rule' => $surchargeRule,
        ]);
    }

    public function update(Request $request, SurchargeRule $surchargeRule): RedirectResponse {
        Gate::authorize('update', $surchargeRule);

        $surchargeRule->update($this->validated($request, $surchargeRule));

        return redirect()->route('admin.surcharge-rules.index')
            ->with('success', __('surcharge.flash.updated'));
    }

    public function destroy(SurchargeRule $surchargeRule): RedirectResponse {
        Gate::authorize('delete', $surchargeRule);

        $surchargeRule->delete();

        return redirect()->route('admin.surcharge-rules.index')
            ->with('success', __('surcharge.flash.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SurchargeRule $rule = null): array {
        $organizationId = $rule->organization_id ?? $this->authUser()->organization_id;

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[a-z0-9][a-z0-9._-]*$/',
                Rule::unique('surcharge_rules', 'code')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->ignore($rule?->id),
            ],
            'label' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(SurchargeKind::class)],
            'window_start' => ['nullable', 'date_format:H:i'],
            'window_end' => ['nullable', 'date_format:H:i'],
            'percentage' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'wage_type_code' => ['nullable', 'string', 'max:20'],
            'tax_free_limit_pct' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'taxable_wage_type_code' => ['nullable', 'string', 'max:20'],
            'priority' => ['required', 'integer', 'min:0', 'max:1000'],
            'active' => ['required', 'boolean'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        $kind = SurchargeKind::from((string) $data['kind']);
        if ($kind->requiresWindow()) {
            $request->validate([
                'window_start' => ['required', 'date_format:H:i'],
                'window_end' => ['required', 'date_format:H:i'],
            ]);
        } else {
            $data['window_start'] = null;
            $data['window_end'] = null;
        }

        // Steuer-Split (Rang 36): liegt die steuerfreie Obergrenze unter dem
        // Prozentsatz, entsteht ein steuerpflichtiger Anteil — der braucht
        // eine eigene Lohnart.
        $limit = isset($data['tax_free_limit_pct']) ? (float) $data['tax_free_limit_pct'] : null;
        if ($limit !== null && $limit < (float) $data['percentage']) {
            $request->validate([
                'taxable_wage_type_code' => ['required', 'string', 'max:20'],
            ], [
                'taxable_wage_type_code.required' => (string) __('surcharge.validation.taxable_wage_type_required'),
            ]);
        }

        $data['active'] = (bool) $data['active'];

        return $data;
    }
}
