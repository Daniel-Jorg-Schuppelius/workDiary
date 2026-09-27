<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InvestmentSupplierRatingController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Investments;

use App\Enums\User\Permission as P;
use App\Http\Controllers\Controller;
use App\Models\Investments\InvestmentCase;
use App\Models\Supplier\Supplier;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Investments\InvestmentSupplierRatingService;
use App\Support\{ErrorText, Sqid};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/** Lieferantenbewertung über Investitionen (MVP-928). */
class InvestmentSupplierRatingController extends Controller {
    public function __construct(private readonly InvestmentSupplierRatingService $ratings) {}

    public function index(): View {
        Gate::authorize(P::InvestmentViewAny->value);

        return view('investments.supplier-ratings', ['rows' => $this->ratings->overview()]);
    }

    public function store(Request $request, InvestmentCase $case): RedirectResponse {
        Gate::authorize('update', $case);
        $request->merge(['supplier_id' => Sqid::decodeOrNumeric(Supplier::class, $request->string('supplier_id')->toString())]);
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', new ExistsInCurrentOrganization('suppliers')],
            'schedule_score' => ['required', 'integer', 'min:1', 'max:5'],
            'cost_score' => ['required', 'integer', 'min:1', 'max:5'],
            'quality_score' => ['required', 'integer', 'min:1', 'max:5'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->ratings->rate($case, Supplier::query()->findOrFail((int) $data['supplier_id']), [
                'schedule_score' => (int) $data['schedule_score'],
                'cost_score' => (int) $data['cost_score'],
                'quality_score' => (int) $data['quality_score'],
                'note' => $data['note'] ?? null,
            ], $request->user() ?? abort(401));
        } catch (RuntimeException $e) {
            return back()->with('error', ErrorText::for($e));
        }

        return back()->with('success', __('investment.supplier_rating.flash.saved'));
    }
}
