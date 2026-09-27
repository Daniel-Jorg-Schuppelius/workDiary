<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentFinancingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\Investments\InvestmentFinancingKind;
use App\Http\Controllers\Controller;
use App\Models\Investments\{InvestmentCase, InvestmentFinancingVariant, InvestmentOption};
use App\Services\Investments\FinancingComparison;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Finanzierungsvergleich je Investitionsvariante (Feature 069, MVP-907). */
class InvestmentFinancingController extends Controller {
    public function show(InvestmentCase $case, InvestmentOption $option, FinancingComparison $comparison): View {
        Gate::authorize('view', $case);
        abort_unless($option->investment_case_id === $case->id, 404);

        return view('investments.financing', [
            'case' => $case,
            'option' => $option,
            'rows' => $comparison->evaluate($option->load('financingVariants')),
            'canEdit' => Gate::allows('update', $case),
        ]);
    }

    public function store(Request $request, InvestmentCase $case, InvestmentOption $option): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($option->investment_case_id === $case->id, 404);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(InvestmentFinancingKind::class)],
            'down_payment_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:kind,loan'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600', 'required_unless:kind,purchase'],
            'rate_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'required_if:kind,lease'],
            'residual_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        InvestmentFinancingVariant::query()->create([
            'organization_id' => $case->organization_id,
            'investment_option_id' => $option->id,
            'kind' => $data['kind'],
            'down_payment_amount' => $data['down_payment_amount'] ?? 0,
            'interest_rate' => $data['interest_rate'] ?? null,
            'term_months' => $data['term_months'] ?? null,
            'rate_amount' => $data['rate_amount'] ?? null,
            'residual_amount' => $data['residual_amount'] ?? 0,
            'fee_amount' => $data['fee_amount'] ?? 0,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()->route('investments.options.financing', [$case, $option])->with('success', __('investment.financing.saved'));
    }

    public function destroy(InvestmentCase $case, InvestmentOption $option, InvestmentFinancingVariant $variant): RedirectResponse {
        Gate::authorize('update', $case);
        abort_unless($option->investment_case_id === $case->id && $variant->investment_option_id === $option->id, 404);
        $variant->delete();

        return redirect()->route('investments.options.financing', [$case, $option])->with('success', __('investment.financing.deleted'));
    }
}
