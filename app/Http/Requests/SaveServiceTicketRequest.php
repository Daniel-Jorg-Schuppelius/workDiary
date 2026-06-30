<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveServiceTicketRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\ServiceTicket\{ServiceTicketPriority, ServiceTicketSource};
use Illuminate\Validation\Rules\Enum;

class SaveServiceTicketRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', new Enum(ServiceTicketPriority::class)],
            'source' => ['nullable', new Enum(ServiceTicketSource::class)],
            'source_reference' => ['nullable', 'string', 'max:120'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}
