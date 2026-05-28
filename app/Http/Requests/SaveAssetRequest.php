<?php
/*
 * Created on   : Sun May 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveAssetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Asset\{AssetClass, AssetStatus};
use App\Http\Requests\Concerns\DecodesSqidInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveAssetRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer::class,
        'room_id' => \App\Models\Room::class,
    ];

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        $orgId = $this->user()?->organization_id;
        $categoryCodes = array_keys((array) config('asset_categories', []));

        return [
            'asset_class' => ['required', new Enum(AssetClass::class)],
            'category_code' => ['nullable', 'string', 'max:64', Rule::in($categoryCodes)],
            'name' => ['required', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
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
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where(
                    fn($query) => $query->where('organization_id', $orgId)
                ),
            ],
            'status' => ['required', new Enum(AssetStatus::class), Rule::notIn([AssetStatus::Decommissioned->value])],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void {
        $validator->after(function ($validator): void {
            $customerId = $this->input('customer_id');
            $roomId = $this->input('room_id');
            if ($roomId === null || $roomId === '') {
                return;
            }

            $room = \App\Models\Room::query()->find($roomId);
            if (! $room instanceof \App\Models\Room) {
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
