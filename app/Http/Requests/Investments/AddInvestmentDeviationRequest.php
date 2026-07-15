<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddInvestmentDeviationRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das Dokumentieren einer Abweichung (MVP-206).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class AddInvestmentDeviationRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'kind' => ['required', 'in:budget,schedule,scope,cancellation'],
            'description' => ['required', 'string', 'max:1000'],
            'amount_delta' => ['nullable', 'numeric'],
        ];
    }
}
