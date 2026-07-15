<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisDecisionRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Protokollieren einer Stabsentscheidung (MVP-214).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisDecisionRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'decision' => ['required', 'string', 'max:1000'],
            'rationale' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
