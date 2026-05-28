<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTourRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Tour\TourStatus;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTourRequest extends FormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\User::class,
        'vehicle_id' => \App\Models\Vehicle::class,
    ];

    public function authorize(): bool {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
            'tour_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:200'],
            'start_address' => ['nullable', 'string', 'max:255'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'end_address' => ['nullable', 'string', 'max:255'],
            'end_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'end_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['sometimes', Rule::enum(TourStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (Validator $v): void {
            $vehicleId = $this->input('vehicle_id');
            $date = $this->input('tour_date');
            if (! $vehicleId || ! $date) {
                return;
            }
            $vehicle = Vehicle::query()->find((int) $vehicleId);
            if (! $vehicle instanceof Vehicle || ! $vehicle->isRental()) {
                return;
            }
            if (! $vehicle->isAvailableOn(CarbonImmutable::parse((string) $date))) {
                $v->errors()->add(
                    'vehicle_id',
                    (string) __('Der Mietwagen ist für dieses Datum nicht verfügbar (Mietzeitraum überschritten).')
                );
            }
        });
    }
}
