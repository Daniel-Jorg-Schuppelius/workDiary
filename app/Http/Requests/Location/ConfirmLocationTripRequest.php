<?php
/*
 * Created on   : Thu Oct 15 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConfirmLocationTripRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Location;

use App\Enums\Travel\{TravelLogVehicle, TripKind};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use Illuminate\Validation\Rule;

/** Fahrtvorschlag aus der Spur ins Fahrtenbuch übernehmen (MVP-1185). */
class ConfirmLocationTripRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'vehicle_id' => \App\Models\Fleet\Vehicle::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'vehicle' => ['required', Rule::enum(TravelLogVehicle::class)],
            'vehicle_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
            'trip_kind' => ['required', Rule::enum(TripKind::class)],
            'purpose' => ['nullable', 'string', 'max:255'],
            'odometer_start_km' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'odometer_end_km' => ['nullable', 'integer', 'min:0', 'max:9999999', 'gte:odometer_start_km'],
        ];
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array {
        $data = parent::validated();
        foreach (['odometer_start_km', 'odometer_end_km', 'vehicle_id'] as $field) {
            if (($data[$field] ?? '') === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
