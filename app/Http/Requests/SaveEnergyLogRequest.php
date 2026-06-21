<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveEnergyLogRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Models\EnergyLog;
use Illuminate\Validation\Rule;

class SaveEnergyLogRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
    }

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'vehicle_id' => \App\Models\Vehicle::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'energy_type' => ['required', 'string', Rule::in(EnergyLog::TYPES)],
            'fuel_kind' => ['nullable', 'string', Rule::in(EnergyLog::FUEL_KINDS)],
            'quantity' => ['required', 'numeric', 'min:0', 'max:9999'],
            'cost_total' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'soc_before' => ['nullable', 'integer', 'between:0,100'],
            'soc_after' => ['nullable', 'integer', 'between:0,100'],
            'charger_type' => ['nullable', 'string', Rule::in(EnergyLog::CHARGER_TYPES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
