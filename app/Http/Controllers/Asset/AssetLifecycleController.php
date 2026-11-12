<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AssetLifecycleController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Asset;

use App\Enums\Asset\{AssetOwnership, AssetStatus};
use App\Exceptions\AssetValidationException;
use App\Http\Controllers\Controller;
use App\Models\Asset\Asset;
use App\Models\Customer\Customer;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Asset\{AssetLifecycleService, AssetService};
use App\Support\Sqid;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Lebenszyklus-Aktionen der Asset-Akte (MVP-1574): außer Betrieb nehmen, wieder
 * in Betrieb nehmen (MVP-1628) und Eigentümer wechseln — mit eigenem Recht und eigener Spur.
 */
class AssetLifecycleController extends Controller {
    public function decommissionForm(Asset $asset): View {
        Gate::authorize('decommission', $asset);

        return view('assets._decommission_dialog', [
            'asset' => $asset,
            'isCheckedOut' => $asset->assignments()->whereNull('returned_at')->exists(),
            'activePlanCount' => $asset->maintenancePlans()->where('is_active', true)->count(),
            'activeDutyCount' => $asset->complianceAssignments()->where('is_active', true)->count(),
        ]);
    }

    public function decommission(Request $request, Asset $asset, AssetService $assets): RedirectResponse {
        Gate::authorize('decommission', $asset);

        $data = $request->validate([
            'decommissioned_on' => ['required', 'date', 'before_or_equal:today'],
            'decommission_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $assets->decommission($asset, $this->authUser(), (string) $data['decommissioned_on'], (string) $data['decommission_reason']);
        } catch (AssetValidationException $exception) {
            $message = $exception->errorCode === AssetValidationException::CODE_INVALID_STATUS_TRANSITION
                ? __('asset.decommission.error.not_allowed', ['status' => $asset->status->label()])
                : __($exception->getMessage());

            return back()->withInput()->withErrors(['decommissioned_on' => $message]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('asset.decommission.done'));
    }

    /** Wieder in Betrieb nehmen (MVP-1628): dasselbe Recht wie die Außerbetriebnahme. */
    public function recommissionForm(Asset $asset): View {
        Gate::authorize('decommission', $asset);
        abort_unless($asset->status === AssetStatus::Decommissioned, 404);

        return view('assets._recommission_dialog', ['asset' => $asset]);
    }

    public function recommission(Request $request, Asset $asset, AssetService $assets): RedirectResponse {
        Gate::authorize('decommission', $asset);

        $data = $request->validate([
            'recommission_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $assets->recommission($asset, $this->authUser(), (string) $data['recommission_reason']);
        } catch (AssetValidationException $exception) {
            $message = $exception->errorCode === AssetValidationException::CODE_INVALID_STATUS_TRANSITION
                ? __('asset.recommission.error.not_decommissioned')
                : __($exception->getMessage());

            return back()->withInput()->withErrors(['recommission_reason' => $message]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('asset.recommission.done'));
    }

    public function ownershipForm(Asset $asset): View {
        Gate::authorize('transferOwnership', $asset);
        abort_if($asset->status === AssetStatus::Decommissioned, 404);

        return view('assets._ownership_dialog', [
            'asset' => $asset->load('customer:id,name'),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function transferOwnership(Request $request, Asset $asset, AssetLifecycleService $lifecycle): RedirectResponse {
        Gate::authorize('transferOwnership', $asset);
        abort_if($asset->status === AssetStatus::Decommissioned, 404);

        if ($request->filled('customer_id')) {
            $request->merge(['customer_id' => Sqid::decodeOrNumeric(Customer::class, $request->input('customer_id'))]);
        }

        $data = $request->validate([
            'owned_by' => ['required', Rule::enum(AssetOwnership::class)],
            'customer_id' => [
                'nullable',
                Rule::requiredIf(fn (): bool => $request->input('owned_by') === AssetOwnership::Customer->value),
                'integer',
                new ExistsInCurrentOrganization('customers'),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $change = $lifecycle->changeOwnership(
            $asset,
            $this->authUser(),
            AssetOwnership::from((string) $data['owned_by']),
            isset($data['customer_id']) ? (int) $data['customer_id'] : null,
            $data['note'] ?? null,
        );

        if ($change === null) {
            return back()->withInput()->withErrors(['owned_by' => __('asset.ownership.error.unchanged')]);
        }

        return redirect()->route('assets.show', $asset)->with('success', __('asset.ownership.done'));
    }
}
