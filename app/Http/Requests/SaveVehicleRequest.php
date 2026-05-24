<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveVehicleRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Vehicle\{VehicleOwnership, VehiclePropulsion, VehicleType};
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVehicleRequest extends FormRequest {
    public function authorize(): bool {
        return true;
    }

    protected function prepareForValidation(): void {
        if (! $this->filled('ownership')) {
            $this->merge(['ownership' => VehicleOwnership::Owned->value]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'license_plate' => ['required', 'string', 'max:32'],
            'label' => ['nullable', 'string', 'max:120'],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'propulsion' => ['required', Rule::enum(VehiclePropulsion::class)],
            'ownership' => ['required', Rule::enum(VehicleOwnership::class)],
            'rental_provider' => ['nullable', 'string', 'max:120', 'required_if:ownership,rental'],
            'rental_start' => ['nullable', 'date', 'required_if:ownership,rental'],
            'rental_end' => ['nullable', 'date', 'after_or_equal:rental_start', 'required_if:ownership,rental'],
            'rental_cost_per_day' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'rental_included_km' => ['nullable', 'integer', 'min:0'],
            'rental_extra_cost_per_km' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'default_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'default_rate_per_km' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'tank_capacity_liters' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'battery_capacity_kwh' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'wltp_consumption' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'odometer_km' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
