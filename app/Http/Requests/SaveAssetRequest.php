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
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SaveAssetRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'asset_class' => ['required', new Enum(AssetClass::class)],
            'name' => ['required', 'string', 'max:255'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->where(
                    fn($query) => $query->where('organization_id', $this->user()?->organization_id)
                ),
            ],
            'status' => ['required', new Enum(AssetStatus::class), Rule::notIn([AssetStatus::Decommissioned->value])],
        ];
    }
}
