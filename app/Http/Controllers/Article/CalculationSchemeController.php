<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CalculationSchemeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Article;

use App\Enums\Article\CostKind;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\Article\{Article, CalculationScheme, WageGroup};
use App\Services\Article\ServiceCalculationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, DB, Gate};
use Illuminate\View\View;

/**
 * Kalkulationsschema und Lohngruppen der Organisation (MVP-1055). Pflegen darf,
 * wer Artikel anlegen darf — die Zuschläge bestimmen kalkulierte Preise.
 */
class CalculationSchemeController extends Controller {
    use ResolvesCurrentOrganization;

    public function __construct(private readonly ServiceCalculationService $calculation) {}

    public function edit(): View {
        Gate::authorize('create', Article::class);
        $scheme = $this->calculation->scheme($this->currentOrganization());

        return view('articles.calculation.scheme', [
            'scheme' => $scheme,
            'kinds' => CostKind::cases(),
            'wageGroups' => WageGroup::query()->orderBy('position')->orderBy('name')->get(),
            'averageWage' => $this->calculation->averageWage($scheme),
            'labourHourCost' => $this->calculation->labourHourCost($scheme),
        ]);
    }

    public function update(Request $request): RedirectResponse {
        Gate::authorize('create', Article::class);
        $rules = [
            'average_wage_amount' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'wage_related_percent' => ['required', 'numeric', 'min:0', 'max:500'],
            'wage_ancillary_amount' => ['required', 'numeric', 'min:0', 'max:9999'],
        ];
        foreach (CostKind::cases() as $kind) {
            foreach (['site_overhead_percent', 'general_overhead_percent', 'risk_profit_percent'] as $field) {
                $rules["markups.{$kind->value}.{$field}"] = ['required', 'numeric', 'min:0', 'max:500'];
            }
        }
        $data = $request->validate($rules);
        $organization = $this->currentOrganization();

        DB::transaction(function () use ($data, $organization): void {
            $scheme = CalculationScheme::query()->updateOrCreate(['organization_id' => $organization->id], [
                'average_wage_amount' => $data['average_wage_amount'] ?? null,
                'wage_related_percent' => (string) $data['wage_related_percent'],
                'wage_ancillary_amount' => (string) $data['wage_ancillary_amount'],
                'updated_by' => Auth::id(),
            ]);
            foreach (CostKind::cases() as $kind) {
                $scheme->markups()->updateOrCreate(['cost_kind' => $kind->value], [
                    'organization_id' => $organization->id,
                    ...array_map('strval', $data['markups'][$kind->value]),
                ]);
            }
        });

        return redirect()->route('articles.calculation-scheme.edit')->with('status', __('article.calculation.flash.scheme_saved'));
    }

    public function wageGroupForm(?WageGroup $wageGroup = null): View {
        Gate::authorize('create', Article::class);

        return view('articles.calculation._wage_group_dialog', ['wageGroup' => $wageGroup ?? new WageGroup()]);
    }

    public function storeWageGroup(Request $request): RedirectResponse {
        Gate::authorize('create', Article::class);
        WageGroup::query()->create([
            ...$this->validateWageGroup($request),
            'organization_id' => $this->currentOrganization()->id,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('articles.calculation-scheme.edit')->with('status', __('article.calculation.flash.wage_group_saved'));
    }

    public function updateWageGroup(Request $request, WageGroup $wageGroup): RedirectResponse {
        Gate::authorize('create', Article::class);
        $wageGroup->update($this->validateWageGroup($request));

        return redirect()->route('articles.calculation-scheme.edit')->with('status', __('article.calculation.flash.wage_group_saved'));
    }

    public function destroyWageGroup(WageGroup $wageGroup): RedirectResponse {
        Gate::authorize('create', Article::class);
        $wageGroup->delete();

        return redirect()->route('articles.calculation-scheme.edit')->with('status', __('article.calculation.flash.wage_group_deleted'));
    }

    /** @return array<string, mixed> */
    private function validateWageGroup(Request $request): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'hourly_wage_amount' => ['required', 'numeric', 'min:0', 'max:9999'],
            'headcount' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        return [
            'name' => $data['name'],
            'hourly_wage_amount' => (string) $data['hourly_wage_amount'],
            'headcount' => (int) $data['headcount'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'position' => (int) ($data['position'] ?? 0),
        ];
    }
}
