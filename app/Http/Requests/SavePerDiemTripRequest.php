<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePerDiemTripRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};

class SavePerDiemTripRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
    }

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => \App\Models\Project::class,
        'customer_id' => \App\Models\Customer::class,
        'travel_log_id' => \App\Models\TravelLog::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'country' => ['required', 'string', 'size:2'],
            'purpose' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'workplace_key' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'travel_log_id' => ['nullable', 'integer', 'exists:travel_logs,id'],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'accommodation_provided' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
