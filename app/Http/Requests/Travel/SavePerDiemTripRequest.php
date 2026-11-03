<?php
/*
 * Created on   : Fri May 22 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SavePerDiemTripRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\Travel;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Models\Customer\Customer;
use CommonToolkit\Helper\Data\StringHelper;

class SavePerDiemTripRequest extends BaseFormRequest {
    use DecodesSqidInputs, ParsesOrgLocalDateTimes;

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['started_at', 'ended_at']);
        if ($this->filled('country')) {
            $this->merge(['country' => $this->string('country')->upper()->value()]);
        }
        // Ohne Ort gilt der Ort des gewählten Kunden, das Land nur ohne eigene Angabe (Pilot 2026-11-03).
        if (! $this->filled('location')) {
            $customerId = $this->decodedInputId('customer_id', Customer::class);
            $customer = $customerId !== null ? Customer::query()->find($customerId) : null;
            if ($customer instanceof Customer && StringHelper::trimToNull($customer->address_city) !== null) {
                $this->merge(['location' => $customer->address_city]);
                if (! $this->filled('country') && is_string($customer->country) && strlen($customer->country) === 2) {
                    $this->merge(['country' => strtoupper($customer->country)]);
                }
            }
        }
    }

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'project_id' => \App\Models\Project\Project::class,
        'customer_id' => \App\Models\Customer\Customer::class,
        'travel_log_id' => \App\Models\Travel\TravelLog::class,
    ];

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'country' => ['required', 'string', \Illuminate\Validation\Rule::enum(\CommonToolkit\Enums\CountryCode::class)],
            'purpose' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'workplace_key' => ['nullable', 'string', 'max:100'],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'travel_log_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('travel_logs')],
            'started_at' => ['required', 'date'],
            'ended_at' => ['required', 'date', 'after_or_equal:started_at'],
            'accommodation_provided' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
