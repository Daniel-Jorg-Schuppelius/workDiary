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
            ...$this->conditionOptions(),
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
            ...$this->conditionOptions(),
        ]);
    }

    /**
     * MVP-513: Auswahllisten für Regel-Bedingungen (Team/Standort/Schichttyp).
     *
     * @return array{conditionTeams: \Illuminate\Support\Collection<int, \App\Models\Team>, conditionSites: \Illuminate\Support\Collection<int, \App\Models\Site>, conditionShiftTypes: \Illuminate\Support\Collection<int, \App\Models\ShiftType>}
     */
    private function conditionOptions(): array {
        return [
            'conditionTeams' => \App\Models\Team::query()->orderBy('name')->get(['id', 'name']),
            'conditionSites' => \App\Models\Site::query()->orderBy('name')->get(['id', 'name']),
            'conditionShiftTypes' => \App\Models\ShiftType::query()->orderBy('name')->get(['id', 'name']),
        ];
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

    /**
     * Sqid-Listen der Bedingungs-Selects → geprüfte IDs der eigenen Org.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<int, mixed>  $raw
     * @return list<int>
     */
    private function decodeIds(string $modelClass, array $raw): array {
        $ids = [];
        foreach ($raw as $value) {
            $id = \App\Support\Sqid::decodeOrNumeric($modelClass, (string) $value);
            if ($id !== null && $id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        // Nur Datensätze der eigenen Organisation (Org-Scope aktiv).
        return array_values(array_map('intval', $modelClass::query()->whereIn('id', $ids)->pluck('id')->all()));
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
            // MVP-513: Bedingungen (leer = gilt für alle).
            'condition_team_ids' => ['nullable', 'array'],
            'condition_team_ids.*' => ['string'],
            'condition_site_ids' => ['nullable', 'array'],
            'condition_site_ids.*' => ['string'],
            'condition_shift_type_ids' => ['nullable', 'array'],
            'condition_shift_type_ids.*' => ['string'],
        ]);

        $conditions = array_filter([
            'team_ids' => $this->decodeIds(\App\Models\Team::class, $data['condition_team_ids'] ?? []),
            'site_ids' => $this->decodeIds(\App\Models\Site::class, $data['condition_site_ids'] ?? []),
            'shift_type_ids' => $this->decodeIds(\App\Models\ShiftType::class, $data['condition_shift_type_ids'] ?? []),
        ], static fn (array $ids): bool => $ids !== []);
        unset($data['condition_team_ids'], $data['condition_site_ids'], $data['condition_shift_type_ids']);
        $data['conditions'] = $conditions === [] ? null : $conditions;

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
