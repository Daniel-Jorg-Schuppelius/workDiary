<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisContinuityImpactRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Erfassen eines kritischen Prozesses (BCM, MVP-219).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisContinuityImpactRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'process_name' => ['required', 'string', 'max:200'],
            'rto_hours' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'rpo_hours' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'workaround' => ['nullable', 'string', 'max:1000'],
            'substitute_process' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
