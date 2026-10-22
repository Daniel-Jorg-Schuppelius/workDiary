<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveServiceTicketRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests\ServiceTicket;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource};
use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\Concerns\{DecodesSqidInputs, ParsesOrgLocalDateTimes};
use App\Rules\ExistsInCurrentOrganization;
use Illuminate\Validation\Rules\Enum;

class SaveServiceTicketRequest extends BaseFormRequest {
    use DecodesSqidInputs;
    use ParsesOrgLocalDateTimes;

    /** @var array<string, class-string> */
    protected array $sqidFields = [
        'customer_id' => \App\Models\Customer\Customer::class,
        'asset_id' => \App\Models\Asset\Asset::class,
        'project_id' => \App\Models\Project\Project::class,
        'business_service_id' => \App\Models\ServiceTicket\BusinessService::class,
        'service_offering_id' => \App\Models\Sales\ServiceOffering::class,
    ];

    protected function prepareForValidation(): void {
        $this->mergeOrgLocalToUtc(['reported_at']);
    }

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', new Enum(ServiceTicketPriority::class)],
            'source' => ['nullable', new Enum(ServiceTicketSource::class)],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('customers')],
            'asset_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('assets')],
            'project_id' => ['nullable', 'integer', new \App\Rules\ExistsInCurrentOrganization('projects')],
            'business_service_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('business_services')],
            'service_offering_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('service_offerings')],
            // Auftragsbezug org-gescopt (nie cross-tenant, s. ExistsInCurrentOrganization).
            'diary_entry_id' => ['nullable', 'integer', new ExistsInCurrentOrganization('diary_entries')],
            'reported_at' => ['nullable', 'date', new \App\Rules\TimestampRange()],
        ];
    }
}
