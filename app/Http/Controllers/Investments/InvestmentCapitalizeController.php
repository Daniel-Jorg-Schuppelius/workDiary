<?php
/*
 * Created on   : Sat Sep 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentCapitalizeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\User\Permission;
use App\Http\Controllers\Controller;
use App\Models\Investments\{InvestmentCase, InvestmentLink};
use App\Services\Investments\Contracts\AssetCapitalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{DB, Gate};

/** Genehmigte Investition als Anlage übernehmen (Feature 069/133, MVP-909). */
class InvestmentCapitalizeController extends Controller {
    public function __construct(private readonly AssetCapitalizer $capitalizer) {}

    public function create(InvestmentCase $case): View {
        $this->guard($case);
        $option = $case->options()->where('recommended', true)->first() ?? $case->options()->orderBy('id')->first();

        return view('investments._capitalize_dialog', [
            'case' => $case,
            'preset' => [
                'name' => $option !== null ? $option->title : $case->title,
                'acquisition_cost' => $option !== null ? (string) $option->one_time_cost : (string) ($case->approvedBudget()->amount ?? ''),
                'useful_life_months' => $option?->useful_life_years !== null ? $option->useful_life_years * 12 : null,
                'acquired_on' => now()->toDateString(),
            ],
        ]);
    }

    public function store(Request $request, InvestmentCase $case): RedirectResponse {
        $this->guard($case);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acquired_on' => ['required', 'date'],
            // Aktiviert wird höchstens, was genehmigt ist (authz-a-8).
            'acquisition_cost' => ['required', 'numeric', 'gt:0', 'max:' . (string) $case->approvedBudget()?->amount],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
        ]);
        $actor = $request->user() ?? abort(401);

        DB::transaction(function () use ($case, $actor, $data): void {
            $asset = $this->capitalizer->capitalize($case, $actor, [
                'name' => (string) $data['name'],
                'acquired_on' => (string) $data['acquired_on'],
                'acquisition_cost' => (string) $data['acquisition_cost'],
                'useful_life_months' => (int) $data['useful_life_months'],
            ]);
            InvestmentLink::query()->create([
                'organization_id' => $case->organization_id,
                'investment_case_id' => $case->id,
                'linkable_type' => $asset->getMorphClass(),
                'linkable_id' => $asset->getKey(),
                'created_by' => $actor->id,
            ]);
        });

        return redirect()->route('investments.show', $case)->with('success', __('investment.capitalize.done'));
    }

    private function guard(InvestmentCase $case): void {
        Gate::authorize('update', $case);
        abort_unless($this->capitalizer->available() && $case->approvedBudget() !== null, 404);
        // Die Anlage entsteht im Anlagenverzeichnis — dessen Recht gilt auch über die Brücke (authz-a-8).
        abort_unless(Gate::allows(Permission::AccountingLedgerConfigure->value), 403);
    }
}
