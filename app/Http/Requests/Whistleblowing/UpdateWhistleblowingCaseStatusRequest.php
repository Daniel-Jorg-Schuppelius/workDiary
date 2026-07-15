<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : UpdateWhistleblowingCaseStatusRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Whistleblowing;

use App\Enums\Whistleblowing\CaseStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * Validierung für den Statuswechsel eines Hinweisgeber-Falls (HinSchG).
 * Berechtigung trägt der Controller (WhistleblowingCasePolicy).
 */
class UpdateWhistleblowingCaseStatusRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'to' => ['required', Rule::in(array_column(CaseStatus::cases(), 'value'))],
            'reason' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
