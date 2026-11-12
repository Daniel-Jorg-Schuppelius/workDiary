<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaintenancePlanRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Asset;

use App\Enums\Asset\{MaintenanceDueAction, MaintenanceIntervalKind};
use App\Enums\User\Permission;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\ServiceTicket\SlaContract;
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\{Rule, Validator};
use Illuminate\Validation\Rules\Enum;

class SaveMaintenancePlanRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'sla_contract_id' => SlaContract::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        // Vertragsbindung nur mit Leserecht auf SLA-Verträge; sonst bleibt sie unverändert.
        $user = $this->user();
        $canBindContract = $user instanceof User && $user->can(Permission::SlaContractView->value);

        return [
            'code' => ['nullable', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:180'],
            'interval_kind' => ['required', new Enum(MaintenanceIntervalKind::class)],
            'interval_value' => ['required', 'integer', 'min:1', 'max:999999999'],
            'tolerance_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'next_due_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sla_contract_id' => $canBindContract
                ? ['nullable', 'integer', 'required_if_accepted:is_contractual', new ExistsInCurrentOrganization('sla_contracts')]
                : ['exclude'],
            'is_contractual' => $canBindContract ? ['nullable', 'boolean'] : ['exclude'],
            'due_action' => ['nullable', Rule::enum(MaintenanceDueAction::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array {
        return [
            'sla_contract_id.required_if_accepted' => __('Eine Vertragspflicht braucht einen SLA-Vertrag.'),
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            $contractId = $v->getData()['sla_contract_id'] ?? null;
            $asset = $this->route('asset');
            if ($contractId === null || $v->errors()->has('sla_contract_id') || ! $asset instanceof Asset) {
                return;
            }

            $contract = SlaContract::query()->find((int) $contractId);
            if (! $contract instanceof SlaContract) {
                return;
            }
            if ((bool) $contract->is_ola
                || ($contract->customer_id !== null && (int) $contract->customer_id !== (int) $asset->customer_id)) {
                $v->errors()->add('sla_contract_id', __('Wählen Sie einen Kundenvertrag dieses Kunden oder einen Vertrag ohne Kundenbindung.'));
            }
        });
    }
}
