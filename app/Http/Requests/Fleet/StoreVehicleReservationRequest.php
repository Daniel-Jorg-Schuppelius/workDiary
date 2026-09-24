<?php
/*
 * Created on   : Sat Jun 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreVehicleReservationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Fleet;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Models\Diary\DiaryEntry;
use App\Models\Fleet\Vehicle;

class StoreVehicleReservationRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    // Formularzeiten sind Ortszeit, gespeichert wird UTC (MVP-823).
    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['reserved_from', 'reserved_to']);
    }

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'vehicle_id' => Vehicle::class,
        'diary_entry_id' => DiaryEntry::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'vehicle_id' => ['required', 'integer', new \App\Rules\ExistsInCurrentOrganization('vehicles')],
            'diary_entry_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('diary_entries')],
            'reserved_from' => ['required', 'date', new \App\Rules\TimestampRange()],
            'reserved_to' => ['required', 'date', 'after:reserved_from', new \App\Rules\TimestampRange()],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
