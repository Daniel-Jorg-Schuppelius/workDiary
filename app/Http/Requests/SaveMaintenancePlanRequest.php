<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SaveMaintenancePlanRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Requests;

use App\Enums\Asset\MaintenanceIntervalKind;
use Illuminate\Validation\Rules\Enum;

class SaveMaintenancePlanRequest extends BaseFormRequest {

    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'code' => ['nullable', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:180'],
            'interval_kind' => ['required', new Enum(MaintenanceIntervalKind::class)],
            'interval_value' => ['required', 'integer', 'min:1'],
            'tolerance_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'procedure_template_code' => ['nullable', 'string', 'max:60'],
            'next_due_on' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
