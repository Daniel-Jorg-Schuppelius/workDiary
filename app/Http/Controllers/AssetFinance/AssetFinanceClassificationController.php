<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetFinanceClassificationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\AssetFinance;

use App\Http\Controllers\Controller;
use App\Models\AssetFinance\AssetFinanceContract;
use App\Services\AssetFinance\AssetFinanceClassificationService;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;

/** IFRS-16-/HGB-Einschätzung am Leasingvertrag (MVP-947), Recht wie die vertraulichen Konditionen. */
class AssetFinanceClassificationController extends Controller {
    public function update(Request $request, AssetFinanceContract $contract, AssetFinanceClassificationService $classification): RedirectResponse {
        Gate::authorize('finance', $contract);
        $data = $request->validate([
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'asset_value_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);
        $data['is_special_lease'] = $request->boolean('is_special_lease');
        $classification->save($contract, $data, $this->authUser());

        return back()->with('success', __('asset_finance.classification.flash.saved'));
    }
}
