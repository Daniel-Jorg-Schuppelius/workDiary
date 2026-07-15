<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : StoreCrisisCaseRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Crisis;

use App\Http\Requests\BaseFormRequest;
use App\Models\Crisis\CrisisCase;

/**
 * Validierung für das Eröffnen einer Krisenakte (Feature 070, MVP-212).
 * Berechtigung trägt der Controller (CrisisCasePolicy).
 */
class StoreCrisisCaseRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'in:' . implode(',', CrisisCase::CATEGORIES)],
            'severity' => ['required', 'in:minor,major,critical'],
            'trigger_source' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'affected_summary' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
