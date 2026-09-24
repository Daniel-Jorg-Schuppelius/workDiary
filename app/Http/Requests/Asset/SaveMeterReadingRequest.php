<?php
/*
 * Created on   : Thu May 28 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMeterReadingRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Asset;

use App\Http\Requests\Concerns\ParsesOrgLocalDateTimes;
use App\Http\Requests\BaseFormRequest;

class SaveMeterReadingRequest extends BaseFormRequest {
    use ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['read_at']);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'asset_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('assets')],
            'read_at' => ['nullable', 'date', new \App\Rules\TimestampRange()],
            'value' => ['required', 'numeric', 'min:0', 'max:99999999999999.9999'],
            'unit' => ['required', 'string', 'max:16'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_estimated' => ['nullable', 'boolean'],
            'photo_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
