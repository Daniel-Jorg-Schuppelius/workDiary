<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePerDiemRateRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

class SavePerDiemRateRequest extends BaseFormRequest {
    protected function prepareForValidation(): void {
        if ($this->filled('currency')) {
            $this->merge(['currency' => $this->string('currency')->upper()->value()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array {
        return [
            'country' => ['required', 'string', 'size:2'],
            'region_label' => ['nullable', 'string', 'max:100'],
            'valid_from' => ['required', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'full_day_amount' => ['required', 'numeric', 'min:0'],
            'partial_day_amount' => ['required', 'numeric', 'min:0'],
            'overnight_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', \Illuminate\Validation\Rule::enum(\CommonToolkit\Enums\CurrencyCode::class)],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
