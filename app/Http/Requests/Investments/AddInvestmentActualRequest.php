<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AddInvestmentActualRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für das manuelle Erfassen eines Ist-Werts (MVP-205).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class AddInvestmentActualRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'amount' => ['required', 'numeric', 'min:-999999999', 'max:999999999'],
            'occurred_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
