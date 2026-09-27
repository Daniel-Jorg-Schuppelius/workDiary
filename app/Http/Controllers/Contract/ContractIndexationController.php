<?php
/*
 * Created on   : Sun Sep 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContractIndexationController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Contract;

use App\Enums\Contract\IndexationMethod;
use App\Http\Controllers\Controller;
use App\Models\Contract\{Contract, ContractIndexation};
use App\Services\Contract\ContractIndexationService;
use App\Support\ErrorText;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

/** Indexanpassung am Vertrag (MVP-952): Basisindex pflegen, Vorschlag rechnen, übernehmen oder verwerfen. */
class ContractIndexationController extends Controller {
    public function edit(Contract $contract): View {
        Gate::authorize('update', $contract);

        return view('contracts._indexation_dialog', ['contract' => $contract, 'isDialog' => true]);
    }

    public function update(Request $request, Contract $contract): RedirectResponse {
        Gate::authorize('update', $contract);
        $data = $request->validate([
            'indexation_base_value' => ['nullable', 'numeric', 'min:1', 'max:9999'],
            'indexation_base_period' => ['nullable', 'date_format:Y-m'],
            'indexation_threshold_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'indexation_pass_through_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $contract->forceFill([
            'indexation_method' => IndexationMethod::ConsumerPriceIndex,
            'indexation_base_value' => $data['indexation_base_value'] ?? null,
            'indexation_base_period_on' => isset($data['indexation_base_period']) ? $data['indexation_base_period'] . '-01' : null,
            'indexation_threshold_percent' => $data['indexation_threshold_percent'] ?? null,
            'indexation_pass_through_percent' => $data['indexation_pass_through_percent'] ?? null,
        ])->save();

        return back()->with('success', __('contract.indexation.flash.saved'));
    }

    public function propose(Contract $contract, ContractIndexationService $service): RedirectResponse {
        Gate::authorize('update', $contract);
        $indexation = $service->propose($contract);

        return back()->with('success', $indexation !== null ? __('contract.indexation.flash.proposed') : __('contract.indexation.flash.none'));
    }

    public function apply(Request $request, ContractIndexation $indexation, ContractIndexationService $service): RedirectResponse {
        Gate::authorize('update', $indexation->contract);
        $data = $request->validate([
            'effective_on' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            $service->apply($indexation, $this->authUser(), isset($data['effective_on']) ? CarbonImmutable::parse($data['effective_on']) : null, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('contract.indexation.flash.applied'));
    }

    public function dismiss(Request $request, ContractIndexation $indexation, ContractIndexationService $service): RedirectResponse {
        Gate::authorize('update', $indexation->contract);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        try {
            $service->dismiss($indexation, $this->authUser(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['status' => ErrorText::for($e)]);
        }

        return back()->with('success', __('contract.indexation.flash.dismissed'));
    }
}
