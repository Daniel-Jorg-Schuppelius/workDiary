<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RejectInvestmentBudgetRequest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Requests\Investments;

use App\Http\Requests\BaseFormRequest;

/**
 * Validierung für die Ablehnung eines Budgetantrags (MVP-203).
 * Berechtigung trägt der Controller (InvestmentCasePolicy).
 */
class RejectInvestmentBudgetRequest extends BaseFormRequest {
    /** @return array<string, mixed> */
    public function rules(): array {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
