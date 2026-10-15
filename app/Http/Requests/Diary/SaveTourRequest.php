<?php
/*
 * Created on   : Sun May 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveTourRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Diary;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\Fleet\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;

class SaveTourRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'user_id' => \App\Models\Platform\User::class,
        'vehicle_id' => \App\Models\Fleet\Vehicle::class,
        'start_site_id' => \App\Models\Facility\Site::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'user_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization()],
            'vehicle_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
            'tour_date' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:200'],
            'start_address' => ['nullable', 'string', 'max:255'],
            'start_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'start_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'end_address' => ['nullable', 'string', 'max:255'],
            'end_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'end_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'start_site_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('sites')],
        ];
    }

    public function withValidator(Validator $validator): void {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            // getData() statt input(): der Validator arbeitet auf den in
            // validationData() Sqid-DEKODIERTEN Werten — input() enthielte
            // noch den Roh-Sqid und der Check liefe still ins Leere.
            $data = $v->getData();
            $vehicleId = $data['vehicle_id'] ?? null;
            $date = $data['tour_date'] ?? null;
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
