<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAssetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Asset;

use App\Enums\Asset\{AssetClass, AssetStatus};
use App\Enums\User\Permission;
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidOrNumericInputs;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\ServiceTicket\SlaContract;
use App\Rules\ExistsInCurrentOrganization;
use App\Services\Asset\AssetCategoryCatalog;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveAssetRequest extends BaseFormRequest {
    use DecodesSqidOrNumericInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'product_id' => \App\Models\Article\Product::class,
        'sla_contract_id' => SlaContract::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        $orgId = $this->user()?->organization_id;
        $asset = $this->route('asset');
        $categoryCodes = array_keys(app(AssetCategoryCatalog::class)->options(
            $orgId === null ? null : (int) $orgId,
            $asset instanceof Asset ? $asset->category_code : null,
        ));
        // Vertragsbindung nur mit Leserecht auf SLA-Verträge; sonst bleibt sie unverändert.
        $user = $this->user();
        $canBindContract = $user instanceof User && $user->can(Permission::SlaContractView->value);

        return [
            'asset_class' => ['required', new Enum(AssetClass::class)],
            'category_code' => ['nullable', 'string', 'max:64', Rule::in($categoryCodes)],
            'name' => ['required', 'string', 'max:180'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            // Typ-Zuordnung (produktmodell-konzept.md, MVP-370).
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(
                    fn($query) => $query->where('organization_id', $orgId)
                ),
            ],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'inventory_no' => ['nullable', 'string', 'max:120'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $orgId)
                ),
            ],
            'foreign_customer_id' => [
                'nullable',
                'integer',
                Rule::exists('foreign_customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $orgId)
                ),
            ],
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where(
                    fn($query) => $query->where('organization_id', $orgId)
                ),
            ],
            // Außer Betrieb setzt nur die eigene Aktion; ein so stillgelegtes Asset bleibt bearbeitbar.
            'status' => ['required', new Enum(AssetStatus::class), Rule::notIn(
                $asset instanceof Asset && $asset->status === AssetStatus::Decommissioned ? [] : [AssetStatus::Decommissioned->value],
            )],
            'ownership_note' => ['nullable', 'string', 'max:1000'],
            'sla_contract_id' => $canBindContract
                ? ['nullable', 'integer', new ExistsInCurrentOrganization('sla_contracts')]
                : ['exclude'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['nullable', 'string', 'max:64'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void {
        // Gleiche Regel wie am Wartungsplan, gemessen am Kunden nach dem Speichern; projektgebundene
        // Verträge gelten nur über ihr Projekt.
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $data = $v->getData();
            $contractId = $data['sla_contract_id'] ?? null;
            if ($contractId === null || $contractId === '' || $v->errors()->has('sla_contract_id')) {
                return;
            }
            $contract = SlaContract::query()->find((int) $contractId);
            if (! $contract instanceof SlaContract) {
                return;
            }
            $customerId = ($data['customer_id'] ?? null) !== null && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null;
            if ($contract->project_id !== null) {
                $v->errors()->add('sla_contract_id', __('Ein projektgebundener Vertrag gilt nur über sein Projekt und lässt sich nicht an ein Asset binden.'));
            } elseif ((bool) $contract->is_ola || ($contract->customer_id !== null && (int) $contract->customer_id !== $customerId)) {
                $v->errors()->add('sla_contract_id', __('Wählen Sie einen Kundenvertrag dieses Kunden oder einen Vertrag ohne Kundenbindung.'));
            }
        });

        $validator->after(function ($validator): void {
            $customerId = $this->input('customer_id');
            $foreignCustomerId = $this->input('foreign_customer_id');

            if ($foreignCustomerId !== null && $foreignCustomerId !== '') {
                if ($customerId === null || $customerId === '') {
                    $validator->errors()->add(
                        'foreign_customer_id',
                        __('Ein Fremdkunde kann nur einem Asset mit Kunde zugeordnet werden.')
                    );
                } else {
                    $foreignCustomer = \App\Models\Customer\ForeignCustomer::query()->find($foreignCustomerId);
                    if (
                        $foreignCustomer instanceof \App\Models\Customer\ForeignCustomer
                        && (int) $foreignCustomer->customer_id !== (int) $customerId
                    ) {
                        $validator->errors()->add(
                            'foreign_customer_id',
                            __('Der gewählte Fremdkunde gehört nicht zum gewählten Kunden.')
                        );
                    }
                }
            }

            $roomId = $this->input('room_id');
            if ($roomId === null || $roomId === '') {
                return;
            }

            $room = \App\Models\Facility\Room::query()->find($roomId);
            if (! $room instanceof \App\Models\Facility\Room) {
                return; // Existenz wird bereits durch exists-Rule abgedeckt.
            }

            if (
                $room->customer_id !== null
                && $customerId !== null && $customerId !== ''
                && (int) $room->customer_id !== (int) $customerId
            ) {
                $validator->errors()->add(
                    'room_id',
                    __('Der gewählte Raum gehört nicht zum gewählten Kunden.')
                );
            }
        });
    }
}
