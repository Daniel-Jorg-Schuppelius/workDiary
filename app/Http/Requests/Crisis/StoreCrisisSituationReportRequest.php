<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisSituationReportRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für einen neuen Lagebericht (MVP-214).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisSituationReportRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'content' => ['required', 'string', 'max:10000'],
            'risks' => ['nullable', 'string', 'max:5000'],
            'communication_status' => ['nullable', 'string', 'max:5000'],
            'recovery_status' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
