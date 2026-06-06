<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveKeyHandoverRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\KeyHandover\KeyHandoverDirection;
use App\Http\Requests\Concerns\ParsesOrgLocalDateTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SaveKeyHandoverRequest extends FormRequest {
    use ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['occurred_at']);
    }

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'direction' => ['required', new Enum(KeyHandoverDirection::class)],
            'person_name' => ['required', 'string', 'max:180'],
            'person_reference' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'occurred_at' => ['nullable', 'date'],
            'expected_return_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'signature_token' => ['nullable', 'string', 'max:64'],
        ];
    }
}
