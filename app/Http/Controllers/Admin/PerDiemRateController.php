<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PerDiemRateController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavePerDiemRateRequest;
use App\Models\PerDiemRate;
use App\Support\SortableQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PerDiemRateController extends Controller {
    public function index(Request $request): View {
        Gate::authorize('viewAny', PerDiemRate::class);

        $query = PerDiemRate::query();
        $country = $request->string('country')->toString();
        if ($country !== '') {
            $query->forCountry($country);
        }

        [$sort, $dir] = SortableQuery::apply($query, $request, [
            'country' => 'country',
            'region' => 'region_label',
            'valid_from' => 'valid_from',
            'valid_to' => 'valid_to',
            'full' => 'full_day_amount',
            'partial' => 'partial_day_amount',
        ], 'valid_from', 'desc');

        $rates = $query->paginate(25)->withQueryString();

        return view('admin.per-diem-rates.index', compact('rates', 'sort', 'dir', 'country'));
    }

    public function create(): View {
        Gate::authorize('create', PerDiemRate::class);

        return view('admin.per-diem-rates._form_dialog', [
            'rate' => new PerDiemRate(['country' => 'DE', 'currency' => 'EUR']),
        ]);
    }

    public function store(SavePerDiemRateRequest $request): RedirectResponse {
        Gate::authorize('create', PerDiemRate::class);

        PerDiemRate::create($request->validated());

        return redirect()->route('admin.per-diem-rates.index')
            ->with('success', __('Pauschalensatz angelegt.'));
    }

    public function edit(PerDiemRate $perDiemRate): View {
        Gate::authorize('update', $perDiemRate);

        return view('admin.per-diem-rates._form_dialog', [
            'rate' => $perDiemRate,
        ]);
    }

    public function update(SavePerDiemRateRequest $request, PerDiemRate $perDiemRate): RedirectResponse {
        Gate::authorize('update', $perDiemRate);

        $perDiemRate->update($request->validated());

        return redirect()->route('admin.per-diem-rates.index')
            ->with('success', __('Pauschalensatz aktualisiert.'));
    }

    public function destroy(PerDiemRate $perDiemRate): RedirectResponse {
        Gate::authorize('delete', $perDiemRate);

        $perDiemRate->delete();

        return redirect()->route('admin.per-diem-rates.index')
            ->with('success', __('Pauschalensatz gelöscht.'));
    }
}
