<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SlaContractQuotaController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\ServiceTicket;

use App\Enums\ServiceTicket\SlaQuotaPeriod;
use App\Enums\User\Permission;
use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Http\Controllers\Controller;
use App\Models\ServiceTicket\{SlaContract, SlaContractQuota};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Inklusivzeit-Kontingente am SLA-Vertrag (Feature 010): Zeitraum, Inklusivzeit,
 * Warnschwelle, Pauschale und Mehrstundensatz. Ein Zeitraum je Vertrag.
 */
class SlaContractQuotaController extends Controller {
    use ResolvesCurrentOrganization;

    public function create(SlaContract $slaContract): View {
        Gate::authorize(Permission::SlaContractManage->value);

        return view('sla-contracts._quota_dialog', ['contract' => $slaContract, 'quota' => null]);
    }

    public function store(Request $request, SlaContract $slaContract): RedirectResponse {
        Gate::authorize(Permission::SlaContractManage->value);

        SlaContractQuota::query()->create($this->validated($request, $slaContract) + [
            'organization_id' => (int) $this->currentOrganization()->id,
            'sla_contract_id' => $slaContract->id,
        ]);

        return redirect()->route('sla-contracts.show', $slaContract)->with('success', __('sla.quota.saved'));
    }

    public function edit(SlaContract $slaContract, SlaContractQuota $quota): View {
        Gate::authorize(Permission::SlaContractManage->value);
        abort_unless((int) $quota->sla_contract_id === (int) $slaContract->id, 404);

        return view('sla-contracts._quota_dialog', ['contract' => $slaContract, 'quota' => $quota]);
    }

    public function update(Request $request, SlaContract $slaContract, SlaContractQuota $quota): RedirectResponse {
        Gate::authorize(Permission::SlaContractManage->value);
        abort_unless((int) $quota->sla_contract_id === (int) $slaContract->id, 404);

        $quota->update($this->validated($request, $slaContract, $quota));

        return redirect()->route('sla-contracts.show', $slaContract)->with('success', __('sla.quota.saved'));
    }

    public function destroy(SlaContract $slaContract, SlaContractQuota $quota): RedirectResponse {
        Gate::authorize(Permission::SlaContractManage->value);
        abort_unless((int) $quota->sla_contract_id === (int) $slaContract->id, 404);

        $quota->delete();

        return redirect()->route('sla-contracts.show', $slaContract)->with('success', __('sla.quota.deleted'));
    }

    /** @return array{period_kind: string, included_minutes: int, warn_threshold_pct: int, flat_fee: ?string, overage_rate: ?string} */
    private function validated(Request $request, SlaContract $contract, ?SlaContractQuota $quota = null): array {
        $data = $request->validate([
            'period_kind' => [
                'required',
                Rule::enum(SlaQuotaPeriod::class),
                Rule::unique('sla_contract_quotas', 'period_kind')->where('sla_contract_id', $contract->id)->ignore($quota?->id),
            ],
            'included_hours' => ['required', 'numeric', 'min:0', 'max:100000'],
            'warn_threshold_pct' => ['required', 'integer', 'min:1', 'max:100'],
            'flat_fee' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'overage_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        return [
            'period_kind' => (string) $data['period_kind'],
            'included_minutes' => (int) round((float) $data['included_hours'] * 60),
            'warn_threshold_pct' => (int) $data['warn_threshold_pct'],
            'flat_fee' => isset($data['flat_fee']) ? (string) $data['flat_fee'] : null,
            'overage_rate' => isset($data['overage_rate']) ? (string) $data['overage_rate'] : null,
        ];
    }
}
