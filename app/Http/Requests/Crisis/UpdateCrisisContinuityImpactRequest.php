<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateCrisisContinuityImpactRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crisis\CrisisContinuityImpact;

/**
 * Validierung für die Wiederanlauf-Statuspflege (BCM, MVP-219).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class UpdateCrisisContinuityImpactRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'status' => ['required', 'in:' . implode(',', CrisisContinuityImpact::STATUSES)],
            'residual_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
