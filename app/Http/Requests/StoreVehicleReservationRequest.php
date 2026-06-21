<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreVehicleReservationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\DecodesSqidInputs;
use App\Models\{DiaryEntry, Vehicle};
use Illuminate\Validation\Rule;

class StoreVehicleReservationRequest extends BaseFormRequest {
    use DecodesSqidInputs;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'vehicle_id' => Vehicle::class,
        'diary_entry_id' => DiaryEntry::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')],
            'diary_entry_id' => ['nullable', 'integer', Rule::exists('diary_entries', 'id')],
            'reserved_from' => ['required', 'date'],
            'reserved_to' => ['required', 'date', 'after:reserved_from'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
